<?php

namespace App\Services;

use App\Models\PaperExtract;
use App\Models\PaperExtractQuestion;
use App\Models\PaperExtractQuestionMedia;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PaperExtractionService
{
    /**
     * Process paper extraction from uploaded file
     *
     * @param PaperExtract $paperExtract
     * @return void
     */
    public function processExtraction(PaperExtract $paperExtract): void
    {
        try {
            $paperExtract->update(['extraction_status' => 'processing']);

            $filePath = Storage::disk('public')->path($paperExtract->source_file_path);

            if (!file_exists($filePath)) {
                throw new Exception("Source file not found: {$filePath}");
            }

            // Determine file type and process accordingly
            $extension = pathinfo($filePath, PATHINFO_EXTENSION);

            switch (strtolower($extension)) {
                case 'pdf':
                    $this->processPdf($paperExtract, $filePath);
                    break;
                case 'doc':
                case 'docx':
                    $this->processWord($paperExtract, $filePath);
                    break;
                default:
                    throw new Exception("Unsupported file type: {$extension}");
            }

            $paperExtract->update([
                'extraction_status' => 'completed',
                'total_questions' => $paperExtract->questions()->count(),
            ]);
        } catch (Exception $e) {
            Log::error("Extraction failed for paper extract {$paperExtract->id}: {$e->getMessage()}");
            $paperExtract->update([
                'extraction_status' => 'failed',
                'extraction_error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Process PDF file extraction
     *
     * @param PaperExtract $paperExtract
     * @param string $filePath
     * @return void
     */
    protected function processPdf(PaperExtract $paperExtract, string $filePath): void
    {
        try {
            // Check if PDF parser library is available
            if (!class_exists(\Smalot\PdfParser\Parser::class)) {
                throw new Exception(
                    "PDF parser library not installed. Please run: composer require smalot/pdfparser"
                );
            }

            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();

            // Extract pages count
            $pages = $pdf->getPages();
            $totalPages = count($pages);
            $paperExtract->update(['total_pages' => $totalPages]);

            // Extract images from PDF (before processing text)
            $extractedImages = $this->extractImagesFromPdf($paperExtract, $filePath, $totalPages);

            // Process text to extract questions
            $this->extractQuestionsFromText($paperExtract, $text, $extractedImages);

            // Save extracted text to file
            $extractedFilePath = 'paper_extracts/extracted/' . $paperExtract->id . '_extracted.txt';
            Storage::disk('public')->put($extractedFilePath, $text);
            $paperExtract->update(['extracted_file_path' => $extractedFilePath]);
        } catch (Exception $e) {
            throw new Exception("PDF processing failed: {$e->getMessage()}");
        }
    }

    /**
     * Process Word document extraction
     *
     * @param Paper $paper
     * @param string $filePath
     * @return void
     */
    protected function processWord(PaperExtract $paperExtract, string $filePath): void
    {
        // For Word documents, you would use a library like PhpOffice\PhpWord
        // This is a placeholder implementation
        try {
            // TODO: Implement Word document processing
            // For now, throw an exception
            throw new Exception("Word document processing not yet implemented. Please convert to PDF first.");
        } catch (Exception $e) {
            throw new Exception("Word processing failed: {$e->getMessage()}");
        }
    }

    /**
     * Extract images from PDF file
     *
     * @param Paper $paper
     * @param string $filePath
     * @param int $totalPages
     * @return array Array of extracted images with page numbers
     */
    protected function extractImagesFromPdf(PaperExtract $paperExtract, string $filePath, int $totalPages): array
    {
        $extractedImages = [];

        try {
            // Try to use poppler-utils (pdftoppm) if available
            $popplerAvailable = $this->isPopplerAvailable();

            if ($popplerAvailable) {
                // Use poppler-utils to extract images
                $outputDir = Storage::disk('public')->path('paper_extracts/images/' . $paperExtract->id);
                if (!is_dir($outputDir)) {
                    mkdir($outputDir, 0755, true);
                }

                // Extract images from each page
                for ($page = 1; $page <= $totalPages; $page++) {
                    $pageImages = $this->extractImagesFromPageWithPoppler($filePath, $page, $outputDir, $paperExtract->id);
                    $extractedImages = array_merge($extractedImages, $pageImages);
                }
            } else {
                // Fallback: Try to extract images using PDF parser
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($filePath);
                $pages = $pdf->getPages();

                $outputDir = Storage::disk('public')->path('paper_extracts/images/' . $paperExtract->id);
                if (!is_dir($outputDir)) {
                    mkdir($outputDir, 0755, true);
                }

                foreach ($pages as $pageIndex => $page) {
                    $pageNumber = $pageIndex + 1;
                    $pageImages = $this->extractImagesFromPageWithParser($page, $pageNumber, $outputDir, $paperExtract->id);
                    $extractedImages = array_merge($extractedImages, $pageImages);
                }
            }
        } catch (Exception $e) {
            // Log error but don't fail the entire extraction
            Log::warning("Image extraction failed for paper extract {$paperExtract->id}: {$e->getMessage()}");
        }

        return $extractedImages;
    }

    /**
     * Check if poppler-utils is available
     */
    protected function isPopplerAvailable(): bool
    {
        $output = [];
        $returnVar = 0;
        @exec('pdftoppm -v 2>&1', $output, $returnVar);
        return $returnVar === 0;
    }

    /**
     * Extract images from a PDF page using poppler-utils
     */
    protected function extractImagesFromPageWithPoppler(string $filePath, int $pageNumber, string $outputDir, int $extractId): array
    {
        $images = [];
        $outputPrefix = $outputDir . '/page_' . $pageNumber . '_';

        // Extract images as PNG
        $command = sprintf(
            'pdftoppm -png -f %d -l %d "%s" "%s" 2>&1',
            $pageNumber,
            $pageNumber,
            escapeshellarg($filePath),
            escapeshellarg($outputPrefix)
        );

        exec($command, $output, $returnVar);

        if ($returnVar === 0) {
            // Find generated image files
            $pattern = $outputPrefix . '*.png';
            $files = glob($pattern);

            foreach ($files as $index => $file) {
                $fileName = basename($file);
                $relativePath = 'paper_extracts/images/' . $extractId . '/' . $fileName;

                // Get image dimensions
                $imageInfo = @getimagesize($file);
                $width = $imageInfo ? $imageInfo[0] : null;
                $height = $imageInfo ? $imageInfo[1] : null;

                $images[] = [
                    'file_path' => $relativePath,
                    'file_name' => $fileName,
                    'page_number' => $pageNumber,
                    'position' => $index + 1,
                    'width' => $width,
                    'height' => $height,
                    'mime_type' => 'image/png',
                ];
            }
        }

        return $images;
    }

    /**
     * Extract images from a PDF page using PDF parser (fallback)
     */
    protected function extractImagesFromPageWithParser($page, int $pageNumber, string $outputDir, int $extractId): array
    {
        $images = [];

        try {
            // Try to get images from the page
            $details = $page->getDetails();

            // This is a simplified approach - PDF parser may not extract images perfectly
            // For better results, poppler-utils is recommended
            if (isset($details['XObject'])) {
                $xObjects = $details['XObject'];
                if (is_array($xObjects)) {
                    foreach ($xObjects as $index => $xObject) {
                        // Try to extract image data
                        // Note: This is a basic implementation and may need refinement
                        $imageData = $page->get('XObject');
                        if ($imageData) {
                            // Save image (this is simplified - actual implementation may vary)
                            $fileName = 'page_' . $pageNumber . '_img_' . ($index + 1) . '.png';
                            $filePath = $outputDir . '/' . $fileName;
                            $relativePath = 'paper_extracts/images/' . $extractId . '/' . $fileName;

                            // Note: PDF parser may not extract images directly
                            // This is a placeholder - poppler-utils is recommended
                        }
                    }
                }
            }
        } catch (Exception $e) {
            Log::warning("Failed to extract images from page {$pageNumber}: {$e->getMessage()}");
        }

        return $images;
    }

    /**
     * Extract questions from text content
     *
     * @param Paper $paper
     * @param string $text
     * @param array $extractedImages
     * @return void
     */
    protected function extractQuestionsFromText(PaperExtract $paperExtract, string $text, array $extractedImages = []): void
    {
        // Split text into lines
        $lines = explode("\n", $text);

        // Store all questions and answers separately, then match them
        $questions = [];
        $answers = [];

        $currentQuestion = null;
        $questionNumber = 1;
        $questionText = '';
        $options = [];
        $correctAnswer = null;
        $pageNumber = 1;
        $inAnswerSection = false;
        $inOptionsSection = false;
        $questionStartLine = 0;
        $currentSection = null; // Track current section

        foreach ($lines as $lineNum => $line) {
            $line = trim($line);

            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // Detect section markers (e.g., "Section A", "Part 1", "SECTION I", etc.)
            if (preg_match('/^(Section|Part|SECTION|PART)\s+([A-Z0-9IVX]+)[\.:]*\s*(.*)$/i', $line, $sectionMatches)) {
                $sectionType = ucfirst(strtolower(trim($sectionMatches[1])));
                $sectionIdentifier = trim($sectionMatches[2]);
                $sectionTitle = trim($sectionMatches[3]);

                // Build section name (e.g., "Section A" or "Part 1: Algebra")
                if (!empty($sectionTitle)) {
                    $currentSection = $sectionType . ' ' . $sectionIdentifier . ': ' . $sectionTitle;
                } else {
                    $currentSection = $sectionType . ' ' . $sectionIdentifier;
                }

                // Save previous question if exists before starting new section
                if ($currentQuestion && !empty($questionText)) {
                    $questions[$questionNumber] = [
                        'text' => $questionText,
                        'options' => $options,
                        'correct_answer' => $correctAnswer,
                        'page' => $pageNumber,
                        'line' => $questionStartLine,
                        'section' => $currentSection,
                    ];
                    $questionNumber++;
                    $questionText = '';
                    $options = [];
                    $correctAnswer = null;
                }
                continue;
            }

            // Detect answer section markers
            if (preg_match('/\b(answer|solution|answers|solutions|marking scheme|answer key)\b/i', $line)) {
                // Save previous question if exists before moving to answers
                if ($currentQuestion && !empty($questionText)) {
                    $questions[$questionNumber] = [
                        'text' => $questionText,
                        'options' => $options,
                        'correct_answer' => $correctAnswer,
                        'page' => $pageNumber,
                        'line' => $questionStartLine,
                        'section' => $currentSection,
                    ];
                    $questionNumber++;
                    $questionText = '';
                    $options = [];
                    $correctAnswer = null;
                }
                $inAnswerSection = true;
                continue;
            }

            // Detect question patterns - improved to catch more variations
            // Pattern 1: "1.", "1)", "(1)", "1:", etc.
            if (preg_match('/^[\(]?(\d+)[\.\):]\s*(.+)$/', $line, $matches)) {
                $detectedNumber = (int)$matches[1];

                // Only treat as new question if it's a reasonable number (not too high, and sequential or reset)
                $isNewQuestion = false;
                if ($currentQuestion === null) {
                    $isNewQuestion = true;
                } elseif ($detectedNumber > $currentQuestion && $detectedNumber <= $currentQuestion + 5) {
                    // Sequential question
                    $isNewQuestion = true;
                } elseif ($detectedNumber < $currentQuestion && $detectedNumber <= 10) {
                    // Possible new section or reset
                    $isNewQuestion = true;
                } elseif ($detectedNumber === $currentQuestion + 1) {
                    // Next sequential question
                    $isNewQuestion = true;
                }

                if ($isNewQuestion) {
                    // Save previous question if exists
                    if ($currentQuestion && !empty($questionText)) {
                        $questions[$questionNumber] = [
                            'text' => $questionText,
                            'options' => $options,
                            'correct_answer' => $correctAnswer,
                            'page' => $pageNumber,
                            'line' => $questionStartLine,
                            'section' => $currentSection,
                        ];
                        $questionText = '';
                        $options = [];
                        $correctAnswer = null;
                    }

                    // Start new question
                    $questionNumber = $detectedNumber;
                    $questionText = $matches[2];
                    $currentQuestion = $questionNumber;
                    $questionStartLine = $lineNum;
                    $inAnswerSection = false;
                    $inOptionsSection = false;
                } else {
                    // Continue current question
                    $questionText .= ' ' . $line;
                }
            } elseif (preg_match('/^Question\s+(\d+)[\.:]\s*(.+)$/i', $line, $matches)) {
                // Save previous question if exists
                if ($currentQuestion && !empty($questionText)) {
                    $questions[$questionNumber] = [
                        'text' => $questionText,
                        'options' => $options,
                        'correct_answer' => $correctAnswer,
                        'page' => $pageNumber,
                        'line' => $questionStartLine,
                        'section' => $currentSection,
                    ];
                    $questionNumber++;
                    $questionText = '';
                    $options = [];
                    $correctAnswer = null;
                }

                // Start new question
                $questionNumber = (int)$matches[1];
                $questionText = $matches[2];
                $currentQuestion = $questionNumber;
                $questionStartLine = $lineNum;
                $inAnswerSection = false;
                $inOptionsSection = false;
            } elseif ($inAnswerSection && preg_match('/^(\d+)[\.\)]\s*(.+)$/', $line, $answerMatches)) {
                // Extract answer for specific question number
                $answerQuestionNum = (int)$answerMatches[1];
                $answerText = $answerMatches[2];
                $extractedAnswer = $this->extractCorrectAnswer($answerText);

                $answers[$answerQuestionNum] = [
                    'text' => $answerText,
                    'correct_answer' => $extractedAnswer,
                ];
            } elseif (preg_match('/^([a-eA-E])[\.\)]\s*(.+)$/', $line, $optionMatches) && $currentQuestion && !$inAnswerSection) {
                // Detect multiple choice options (A, B, C, D, E) - only in question section
                $optionLetter = strtoupper($optionMatches[1]);
                $optionText = $optionMatches[2];
                $options[$optionLetter] = $optionText;
                $inOptionsSection = true;
            } elseif (preg_match('/^Answer[:\s]+([a-eA-E\d]+)/i', $line, $answerMatch) && $currentQuestion && !$inAnswerSection) {
                // Extract correct answer (e.g., "Answer: A" or "Answer: 1") - only in question section
                $correctAnswer = strtoupper(trim($answerMatch[1]));
            } elseif ($currentQuestion && !$inAnswerSection) {
                // Continue current question or options
                if ($inOptionsSection && !empty($options)) {
                    // Continue last option
                    $lastKey = array_key_last($options);
                    $options[$lastKey] .= ' ' . $line;
                } else {
                    $questionText .= ' ' . $line;
                }
            } elseif ($inAnswerSection) {
                // Continue answer text for the last answer found
                if (!empty($answers)) {
                    $lastAnswerKey = array_key_last($answers);
                    $answers[$lastAnswerKey]['text'] .= ' ' . $line;
                }
            }

            // Detect page breaks (simple heuristic)
            if (stripos($line, 'page') !== false && preg_match('/\d+/', $line, $pageMatches)) {
                $pageNumber = (int)$pageMatches[0];
            }
        }

        // Save last question if exists
        if ($currentQuestion && !empty($questionText)) {
            $questions[$questionNumber] = [
                'text' => $questionText,
                'options' => $options,
                'correct_answer' => $correctAnswer,
                'page' => $pageNumber,
                'line' => $questionStartLine,
                'section' => $currentSection,
            ];
        }

        // Now save all questions with their matched answers and associate images
        foreach ($questions as $qNum => $questionData) {
            $answerData = $answers[$qNum] ?? null;

            $question = $this->saveQuestion(
                $paperExtract,
                $qNum,
                $questionData['text'],
                $questionData['page'],
                $questionData['options'],
                $answerData['correct_answer'] ?? $questionData['correct_answer'],
                $answerData['text'] ?? '',
                $questionData['section'] ?? null
            );

            // Associate images with this question based on page number
            if ($question && !empty($extractedImages)) {
                $this->associateImagesWithQuestion($question, $questionData['page'], $extractedImages);
            }
        }
    }

    /**
     * Associate images with a question based on page number
     *
     * @param PaperExtractQuestion $question
     * @param int $pageNumber
     * @param array $extractedImages
     * @return void
     */
    protected function associateImagesWithQuestion(PaperExtractQuestion $question, int $pageNumber, array $extractedImages): void
    {
        // Find images on the same page as the question
        $pageImages = array_filter($extractedImages, function ($image) use ($pageNumber) {
            return isset($image['page_number']) && $image['page_number'] == $pageNumber;
        });

        foreach ($pageImages as $image) {
            try {
                PaperExtractQuestionMedia::create([
                    'paper_extract_question_id' => $question->id,
                    'media_type' => 'image', // Can be enhanced to detect charts/diagrams
                    'file_path' => $image['file_path'],
                    'file_name' => $image['file_name'] ?? basename($image['file_path']),
                    'mime_type' => $image['mime_type'] ?? 'image/png',
                    'width' => $image['width'] ?? null,
                    'height' => $image['height'] ?? null,
                    'page_number' => $image['page_number'],
                    'position' => $image['position'] ?? 0,
                    'description' => 'Question ' . ($question->question_number ?? $question->order) . ' image',
                ]);
            } catch (Exception $e) {
                Log::warning("Failed to associate image with question {$question->id}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Save extracted question to database
     *
     * @param PaperExtract $paperExtract
     * @param int $questionNumber
     * @param string $questionText
     * @param int $pageNumber
     * @param array $options
     * @param string|null $correctAnswer
     * @param string $answerText
     * @param string|null $section
     * @return PaperExtractQuestion
     */
    protected function saveQuestion(PaperExtract $paperExtract, int $questionNumber, string $questionText, int $pageNumber, array $options = [], ?string $correctAnswer = null, string $answerText = '', ?string $section = null): PaperExtractQuestion
    {
        // Clean question text
        $cleanText = $this->cleanQuestionText($questionText);

        // Detect question type
        $questionType = $this->detectQuestionType($questionText);

        // Extract keywords
        $keywords = $this->extractKeywords($cleanText);

        // Extract marks if mentioned
        $marks = $this->extractMarks($questionText);

        // Clean answer text
        $cleanAnswerText = !empty($answerText) ? $this->cleanQuestionText($answerText) : null;

        // If we have options but no correct answer, try to extract it from answer text
        if (!empty($options) && empty($correctAnswer) && !empty($answerText)) {
            $correctAnswer = $this->extractCorrectAnswer($answerText);
        }

        return PaperExtractQuestion::create([
            'paper_extract_id' => $paperExtract->id,
            'question_number' => $questionNumber,
            'section' => $section,
            'question_text' => $questionText,
            'question_text_clean' => $cleanText,
            'question_type' => $questionType,
            'marks' => $marks,
            'page_number' => $pageNumber,
            'options' => !empty($options) ? $options : null,
            'correct_answer' => $correctAnswer,
            'answer_explanation' => $cleanAnswerText,
            'topic' => null, // Can be extracted if needed
            'subtopic' => null, // Can be extracted if needed
            'keywords' => $keywords,
            'metadata' => null, // Can be populated if needed
            'order' => $questionNumber,
            'status' => config('constants.statuses.APPROVED'),
        ]);
    }

    /**
     * Clean question text
     *
     * @param string $text
     * @return string
     */
    protected function cleanQuestionText(string $text): string
    {
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Remove special characters but keep punctuation
        $text = trim($text);

        return $text;
    }

    /**
     * Detect question type from text
     *
     * @param string $text
     * @return string|null
     */
    protected function detectQuestionType(string $text): ?string
    {
        $text = strtolower($text);

        if (preg_match('/\b(multiple choice|mcq|choose|select)\b/i', $text)) {
            return 'multiple_choice';
        }

        if (preg_match('/\b(calculate|work out|find|solve)\b/i', $text)) {
            return 'calculation';
        }

        if (preg_match('/\b(explain|describe|discuss|write)\b/i', $text)) {
            return 'essay';
        }

        if (preg_match('/\b(true|false|t\/f)\b/i', $text)) {
            return 'true_false';
        }

        return 'short_answer';
    }

    /**
     * Extract keywords from text
     *
     * @param string $text
     * @return array
     */
    protected function extractKeywords(string $text): array
    {
        // Simple keyword extraction (can be enhanced with NLP)
        $words = str_word_count(strtolower($text), 1);

        // Remove common stop words
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'should', 'could', 'may', 'might', 'must', 'can'];

        $keywords = array_filter($words, function ($word) use ($stopWords) {
            return strlen($word) > 3 && !in_array($word, $stopWords);
        });

        // Return top 10 unique keywords
        return array_values(array_unique(array_slice($keywords, 0, 10)));
    }

    /**
     * Extract marks from question text
     *
     * @param string $text
     * @return int
     */
    protected function extractMarks(string $text): int
    {
        // Look for patterns like "[2 marks]", "(2)", "2 marks", etc.
        if (preg_match('/\[(\d+)\s*marks?\]/i', $text, $matches)) {
            return (int)$matches[1];
        }

        if (preg_match('/\((\d+)\)/', $text, $matches)) {
            return (int)$matches[1];
        }

        if (preg_match('/(\d+)\s*marks?/i', $text, $matches)) {
            return (int)$matches[1];
        }

        return 1; // Default to 1 mark
    }

    /**
     * Extract correct answer from answer text
     *
     * @param string $answerText
     * @return string|null
     */
    protected function extractCorrectAnswer(string $answerText): ?string
    {
        // Look for patterns like "Answer: A", "A)", "Answer A", etc.
        if (preg_match('/answer[:\s]+([a-eA-E\d]+)/i', $answerText, $matches)) {
            return strtoupper(trim($matches[1]));
        }

        // Look for single letter/number at start of line
        if (preg_match('/^([a-eA-E\d])[\.\)]/', $answerText, $matches)) {
            return strtoupper(trim($matches[1]));
        }

        // Look for answer in brackets
        if (preg_match('/\(([a-eA-E\d]+)\)/', $answerText, $matches)) {
            return strtoupper(trim($matches[1]));
        }

        // Look for patterns like "= A", "=A", "is A", etc.
        if (preg_match('/[=\s]+([a-eA-E\d]+)\b/i', $answerText, $matches)) {
            return strtoupper(trim($matches[1]));
        }

        // If answer text is very short (1-2 characters), it might be the answer itself
        $trimmed = trim($answerText);
        if (strlen($trimmed) <= 2 && preg_match('/^[a-eA-E\d]+$/', $trimmed)) {
            return strtoupper($trimmed);
        }

        return null;
    }
}
