<?php

namespace App\Jobs;

use App\Models\Paper;
use App\Models\PaperQuestion;
use App\Models\PaperOption;
use App\Models\PaperAnswer;
use App\Services\PaperDocumentAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessPaperPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $paperId;
    protected $pdfPath;
    protected $pdfMediaId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $paperId, string $pdfPath, int $pdfMediaId = null)
    {
        $this->paperId = $paperId;
        $this->pdfPath = $pdfPath;
        $this->pdfMediaId = $pdfMediaId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Starting PDF processing for paper", [
                'paper_id' => $this->paperId,
                'pdf_path' => $this->pdfPath,
            ]);

            // Paper find karein
            $paper = Paper::find($this->paperId);
            
            if (!$paper) {
                Log::error("Paper not found", ['paper_id' => $this->paperId]);
                return;
            }

            // PDF file check karein
            if (!file_exists($this->pdfPath)) {
                Log::error("PDF file not found", ['pdf_path' => $this->pdfPath]);
                return;
            }

            // Document AI service use karein
            $documentAIService = new PaperDocumentAIService();
            $extractedData = $documentAIService->extractPaperData($this->pdfPath);

            if (!$extractedData['success']) {
                Log::error("PDF extraction failed", [
                    'paper_id' => $this->paperId,
                    'error' => $extractedData['error'] ?? 'Unknown error',
                ]);
                return;
            }

            // Database transaction start karein
            DB::beginTransaction();

            try {
                // Questions save karein
                $questionsSaved = $this->saveQuestions($paper, $extractedData['questions'] ?? []);
                
                // Images save karein (agar chahiye)
                // Note: Images ko media library mein save kar sakte hain
                
                Log::info("PDF processing completed successfully", [
                    'paper_id' => $this->paperId,
                    'questions_saved' => $questionsSaved,
                    'total_questions_extracted' => count($extractedData['questions'] ?? []),
                ]);

                DB::commit();

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            Log::error("Failed to process PDF for paper", [
                'paper_id' => $this->paperId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            // Job fail nahi karein, bas log karein
            // Frontend ko notification bhej sakte hain
        }
    }

    /**
     * Extracted questions ko database mein save karein
     */
    protected function saveQuestions(Paper $paper, array $questions): int
    {
        $savedCount = 0;
        $order = 1;

        foreach ($questions as $questionData) {
            try {
                // Question create karein
                $question = PaperQuestion::create([
                    'paper_id' => $paper->id,
                    'question_text' => $questionData['text'] ?? $questionData['raw_text'] ?? '',
                    'marks' => 1, // Default marks (admin baad mein update kar sakta hai)
                    'duration_in_sec' => 60, // Default duration
                    'order' => $questionData['number'] ?? $order++,
                    'status' => config('constants.statuses.APPROVED', 2),
                ]);

                // Options create karein
                $options = $questionData['options'] ?? [];
                $correctAnswerLabel = $questionData['answer'] ?? null;

                foreach ($options as $optionData) {
                    $option = PaperOption::create([
                        'paper_question_id' => $question->id,
                        'option_text' => $optionData['text'] ?? '',
                        'order' => $this->getOptionOrder($optionData['label'] ?? ''),
                        'status' => config('constants.statuses.APPROVED', 2),
                    ]);

                    // Correct answer identify karein
                    $optionLabel = strtoupper($optionData['label'] ?? '');
                    if ($correctAnswerLabel && strtoupper($correctAnswerLabel) === $optionLabel) {
                        PaperAnswer::create([
                            'paper_option_id' => $option->id,
                            'status' => config('constants.statuses.APPROVED', 2),
                        ]);
                    }
                }

                $savedCount++;

            } catch (Exception $e) {
                Log::warning("Failed to save question", [
                    'paper_id' => $paper->id,
                    'question_data' => $questionData,
                    'error' => $e->getMessage(),
                ]);
                // Continue with next question
            }
        }

        // Total marks update karein
        if ($savedCount > 0) {
            $totalMarks = PaperQuestion::where('paper_id', $paper->id)->sum('marks');
            $paper->update(['total_marks' => $totalMarks]);
        }

        return $savedCount;
    }

    /**
     * Option label se order determine karein
     */
    protected function getOptionOrder(string $label): int
    {
        $label = strtoupper(trim($label));
        
        // A=1, B=2, C=3, etc.
        if (strlen($label) === 1 && ctype_alpha($label)) {
            return ord($label) - ord('A') + 1;
        }
        
        // Number ho to directly use karein
        if (is_numeric($label)) {
            return (int)$label;
        }
        
        return 0;
    }
}


