<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Models\PaperExtractQuestion;
use App\Models\PaperQuestion;
use App\Models\Paper;
use App\Models\PaperExtract;
use Illuminate\Support\Facades\Log;

class ExtractPaperJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param int|null $paperExtractId Paper Extract flow: ID of paper_extracts record
     * @param int|null $paperId        Paper (Create Paper) flow: ID of papers record
     * @param string   $pdfPath        Full filesystem path to the PDF file
     * @param int|null $mediaId        Optional media ID (Paper flow only)
     */
    public function __construct(
        public ?int $paperExtractId = null,
        public ?int $paperId = null,
        public string $pdfPath = '',
        public ?int $mediaId = null
    ) {}

    public function handle(): void
    {
        if ($this->paperExtractId !== null) {
            $this->handlePaperExtract();
            return;
        }

        if ($this->paperId !== null) {
            $this->handlePaper();
            return;
        }

        throw new \InvalidArgumentException('ExtractPaperJob requires either paperExtractId or paperId.');
    }

    /**
     * Run extraction for Paper Extract (paper_extracts + paper_extract_questions).
     */
    protected function handlePaperExtract(): void
    {
        $paperExtract = PaperExtract::findOrFail($this->paperExtractId);

        $outputPath = storage_path('app/extracted/paper_extract_' . $this->paperExtractId . '.json');
        if (!file_exists(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0755, true);
        }

        $pythonPath = base_path('python/paper_extractor_app/venv/Scripts/python');
        $mainPath  = base_path('python/paper_extractor_app/main.py');

        if (!file_exists($pythonPath) || !file_exists($mainPath)) {
            $pythonPath = base_path('python/paper_extract/venv/Scripts/python');
            $mainPath  = base_path('python/paper_extract/main.py');
        }

        $paperExtract->update(['extraction_status' => 'processing']);

        $scriptDir = dirname($mainPath);

        $process = new Process([
            $pythonPath,
            $mainPath,
            '--pdf',
            $this->pdfPath,
            '--output',
            $outputPath
        ]);

        $process->setWorkingDirectory($scriptDir);
        $process->setEnv($this->buildPythonEnv($scriptDir));

        $process->setTimeout(900);
        $process->run();

        $pythonStdout = $process->getOutput();
        $pythonStderr = $process->getErrorOutput();

        if (!$process->isSuccessful()) {
            Log::error('ExtractPaperJob Python process failed', [
                'paper_extract_id' => $this->paperExtractId,
                'stdout' => $pythonStdout,
                'stderr' => $pythonStderr,
            ]);
            $paperExtract->update([
                'extraction_status' => 'failed',
                'extraction_error' => $pythonStderr ?: $pythonStdout ?: 'Extraction process failed',
            ]);
            throw new ProcessFailedException($process);
        }

        Log::info('ExtractPaperJob Python process finished', [
            'paper_extract_id' => $this->paperExtractId,
            'python_stdout' => $pythonStdout,
            'python_stderr' => $pythonStderr ?: null,
        ]);

        if (!file_exists($outputPath)) {
            $paperExtract->update([
                'extraction_status' => 'failed',
                'extraction_error' => 'Python did not produce output file.',
            ]);
            throw new \RuntimeException('Extraction output file not found: ' . $outputPath);
        }

        $rawJson = file_get_contents($outputPath);
        $data = json_decode($rawJson, true) ?: [];

        Log::info('ExtractPaperJob Python result received', [
            'paper_extract_id' => $this->paperExtractId,
            'output_file_keys' => array_keys($data),
            'raw_json_length' => strlen($rawJson),
        ]);

        if (isset($data['status']) && $data['status'] === 'error') {
            Log::error('ExtractPaperJob Python returned error payload', [
                'paper_extract_id' => $this->paperExtractId,
                'message' => $data['message'] ?? null,
                'traceback' => $data['traceback'] ?? null,
            ]);
            $paperExtract->update([
                'extraction_status' => 'failed',
                'extraction_error' => $data['message'] ?? 'Python extraction error',
            ]);
            return;
        }

        $questions = $data['questions'] ?? [];
        $totalPages = (int) ($data['total_pages'] ?? 0);
        $stderrIndicatesFailure = $pythonStderr && (
            str_contains($pythonStderr, 'Failed to import') ||
            str_contains($pythonStderr, 'Required services not initialized')
        );

        if ($stderrIndicatesFailure || (count($questions) === 0 && $totalPages === 0)) {
            $errorMessage = $stderrIndicatesFailure
                ? trim(explode("\n", $pythonStderr)[0] ?? $pythonStderr)
                : 'Extraction returned no questions. Check Python dependencies and PDF.';
            Log::warning('ExtractPaperJob treating as failed: Python reported success but no data', [
                'paper_extract_id' => $this->paperExtractId,
                'python_stderr' => $pythonStderr ?: null,
                'questions_count' => count($questions),
                'total_pages' => $totalPages,
            ]);
            $paperExtract->update([
                'extraction_status' => 'failed',
                'extraction_error' => $errorMessage,
            ]);
            return;
        }

        Log::info('ExtractPaperJob Python questions summary', [
            'paper_extract_id' => $this->paperExtractId,
            'questions_count' => count($questions),
            'first_question_keys' => isset($questions[0]) && is_array($questions[0]) ? array_keys($questions[0]) : null,
            'first_question_sample' => isset($questions[0]) ? $questions[0] : null,
        ]);

        $updatePayload = [
            'extraction_status' => 'completed',
            'total_questions'   => count($questions),
            'total_pages'       => $data['total_pages'] ?? null,
        ];
        if (isset($data['subject'])) {
            $updatePayload['subject'] = $data['subject'];
        }
        if (isset($data['exam_board'])) {
            $updatePayload['exam_board'] = $data['exam_board'];
        }
        if (isset($data['year'])) {
            $updatePayload['year'] = $data['year'];
        }
        if (isset($data['level'])) {
            $updatePayload['level'] = $data['level'];
        }
        $paperExtract->update($updatePayload);

        $created = 0;
        foreach ($questions as $index => $q) {
            if (!is_array($q)) {
                Log::warning('ExtractPaperJob skipping non-array question item', [
                    'paper_extract_id' => $this->paperExtractId,
                    'index' => $index,
                    'type' => gettype($q),
                ]);
                continue;
            }
            PaperExtractQuestion::create([
                'paper_extract_id'    => $this->paperExtractId,
                'question_number'     => $q['number'] ?? $q['question_number'] ?? ($index + 1),
                'section'             => $q['section'] ?? null,
                'question_text'       => $q['text'] ?? $q['question_text'] ?? '',
                'question_text_clean' => $q['text_clean'] ?? $q['question_text_clean'] ?? null,
                'question_type'       => $q['type'] ?? $q['question_type'] ?? null,
                'marks'               => $q['marks'] ?? null,
                'page_number'         => $q['page_number'] ?? $q['page'] ?? null,
                'options'             => $q['options'] ?? null,
                'correct_answer'      => $q['correct_answer'] ?? null,
                'answer_explanation'  => $q['answer_explanation'] ?? null,
                'topic'               => $q['topic'] ?? null,
                'subtopic'            => $q['subtopic'] ?? null,
                'keywords'            => $q['keywords'] ?? null,
                'metadata'            => $q['metadata'] ?? null,
                'order'               => $q['order'] ?? ($index + 1),
                'status'               => config('constants.statuses.APPROVED'),
            ]);
            $created++;
        }

        Log::info('ExtractPaperJob questions saved to database', [
            'paper_extract_id' => $this->paperExtractId,
            'questions_from_python' => count($questions),
            'questions_created' => $created,
        ]);
    }

    /**
     * Run extraction for Paper (papers + paper_questions) – legacy Create Paper flow.
     */
    protected function handlePaper(): void
    {
        $outputPath = storage_path(
            'app/extracted/paper_' . $this->paperId .
                ($this->mediaId ? '_media_' . $this->mediaId : '') .
                '.json'
        );

        if (!file_exists(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0755, true);
        }

        $pythonPath = base_path('python/paper_extractor_app/venv/Scripts/python');
        $mainPath  = base_path('python/paper_extractor_app/main.py');

        if (!file_exists($pythonPath) || !file_exists($mainPath)) {
            $pythonPath = base_path('python/paper_extract/venv/Scripts/python');
            $mainPath  = base_path('python/paper_extract/main.py');
        }

        $scriptDir = dirname($mainPath);

        $process = new Process([
            $pythonPath,
            $mainPath,
            '--pdf',
            $this->pdfPath,
            '--output',
            $outputPath
        ]);

        $process->setWorkingDirectory($scriptDir);
        $process->setEnv($this->buildPythonEnv($scriptDir));

        $process->setTimeout(900);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $data = json_decode(file_get_contents($outputPath), true);

        foreach ($data['questions'] ?? [] as $index => $q) {
            PaperQuestion::create([
                'paper_id' => $this->paperId,
                'question_text' => $q['text'] ?? '',
                'marks' => $q['marks'] ?? null,
                'order' => $index + 1,
                'source_media_id' => $this->mediaId
            ]);
        }

        Paper::where('id', $this->paperId)->update([
            'status' => 'extracted'
        ]);
    }

    /**
     * Build environment for Python subprocess so it matches running from terminal:
     * inherit parent env (PATH etc.), set PYTHONPATH, and ensure Poppler is on PATH.
     */
    protected function buildPythonEnv(string $scriptDir): array
    {
        $env = is_array(getenv()) ? getenv() : [];
        $env = array_filter($env, fn ($v) => is_string($v));

        $env['GOOGLE_API_KEY'] = trim((string) config('services.gemini.key'));
        $env['PYTHONUNBUFFERED'] = '1';
        $env['PYTHONHASHSEED'] = '0';
        $env['PYTHONPATH'] = $scriptDir;

        $popplerPath = trim((string) config('services.poppler_path'));
        if ($popplerPath !== '') {
            $popplerPath = str_replace('\\', '/', $popplerPath);
            $separator = PHP_OS_FAMILY === 'Windows' ? ';' : ':';
            $existingPath = $env['PATH'] ?? $env['Path'] ?? '';
            $newPath = $popplerPath . $separator . $existingPath;
            $env['PATH'] = $newPath;
            if (PHP_OS_FAMILY === 'Windows') {
                $env['Path'] = $newPath;
            }
        }

        return $env;
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ExtractPaperJob failed: ' . $e->getMessage());

        if ($this->paperExtractId !== null) {
            PaperExtract::where('id', $this->paperExtractId)->update([
                'extraction_status' => 'failed',
                'extraction_error'  => $e->getMessage(),
            ]);
        }

        if ($this->paperId !== null) {
            Paper::where('id', $this->paperId)->update([
                'status' => 'failed'
            ]);
        }
    }
}
