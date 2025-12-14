<?php

namespace App\Services;

use Google\Cloud\DocumentAI\V1\DocumentProcessorServiceClient;
use Google\Cloud\DocumentAI\V1\ProcessRequest;
use Google\Cloud\DocumentAI\V1\RawDocument;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PaperDocumentAIService
{
    protected $client;
    protected $projectId;
    protected $location;
    protected $processorId;

    public function __construct()
    {
        $this->projectId = config('services.google_document_ai.project_id');
        $this->location = config('services.google_document_ai.location', 'us');
        $this->processorId = config('services.google_document_ai.processor_id');
        
        // Client initialize karein
        $credentialsPath = config('services.google_document_ai.credentials_path');
        
        if (!$credentialsPath || !file_exists($credentialsPath)) {
            throw new Exception('Google Document AI credentials not found. Please check GOOGLE_APPLICATION_CREDENTIALS in .env');
        }

        $this->client = new DocumentProcessorServiceClient([
            'credentials' => $credentialsPath,
        ]);
    }

    /**
     * PDF file se text, images, aur structured data extract karein
     * Paper module ke liye specific - questions, options, answers identify karein
     */
    public function extractPaperData(string $filePath): array
    {
        try {
            Log::info("Starting PDF extraction for paper", ['file_path' => $filePath]);

            // File read karein
            if (!file_exists($filePath)) {
                throw new Exception("PDF file not found: {$filePath}");
            }

            $fileContent = file_get_contents($filePath);
            $mimeType = mime_content_type($filePath);

            if ($mimeType !== 'application/pdf') {
                throw new Exception("File must be a PDF. Got: {$mimeType}");
            }

            // Processor name
            $processorName = $this->client->processorName(
                $this->projectId,
                $this->location,
                $this->processorId
            );

            // Raw document create karein
            $rawDocument = new RawDocument([
                'content' => $fileContent,
                'mime_type' => $mimeType,
            ]);

            // Process request create karein
            $request = new ProcessRequest([
                'name' => $processorName,
                'raw_document' => $rawDocument,
            ]);

            // Document process karein
            Log::info("Sending document to Google Document AI for processing");
            $response = $this->client->processDocument($request);
            $document = $response->getDocument();

            // Extract data
            $extractedData = [
                'success' => true,
                'text' => $document->getText(),
                'pages' => $document->getPages()->count(),
                'images' => $this->extractImages($document),
                'questions' => $this->extractQuestions($document),
                'tables' => $this->extractTables($document),
                'entities' => $this->extractEntities($document),
            ];

            Log::info("PDF extraction completed successfully", [
                'pages' => $extractedData['pages'],
                'images_count' => count($extractedData['images']),
                'questions_count' => count($extractedData['questions']),
            ]);

            return $extractedData;

        } catch (Exception $e) {
            Log::error('Paper Document AI Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Document se images extract karein
     */
    protected function extractImages($document): array
    {
        $images = [];
        
        try {
            $pages = $document->getPages();
            
            foreach ($pages as $pageIndex => $page) {
                // Images extract karein (agar available hain)
                $pageImages = $page->getDetectedLanguages();
                
                // Layout se images extract karein
                $layout = $page->getLayout();
                if ($layout) {
                    // Image blocks identify karein
                    // Note: Actual implementation depends on Document AI response structure
                }
                
                // Alternative: Extract images from page blocks
                $blocks = $page->getBlocks();
                if ($blocks) {
                    foreach ($blocks as $block) {
                        if ($block->getLayout() && $block->getLayout()->getTextAnchor()) {
                            // Image detection logic yahan add karein
                        }
                    }
                }
            }
        } catch (Exception $e) {
            Log::warning("Error extracting images: " . $e->getMessage());
        }

        return $images;
    }

    /**
     * Document se questions extract karein
     * Pattern-based extraction: "Q1", "Question 1", etc.
     */
    protected function extractQuestions($document): array
    {
        $questions = [];
        
        try {
            $fullText = $document->getText();
            
            // Question patterns identify karein
            // Common patterns: "Q1", "Question 1", "1.", "(1)", etc.
            $questionPatterns = [
                '/Q\s*(\d+)[\.\)]\s*(.+?)(?=Q\s*\d+[\.\)]|$)/is',
                '/Question\s*(\d+)[\.\)]\s*(.+?)(?=Question\s*\d+[\.\)]|$)/is',
                '/(\d+)[\.\)]\s*(.+?)(?=\d+[\.\)]|$)/is',
            ];

            foreach ($questionPatterns as $pattern) {
                preg_match_all($pattern, $fullText, $matches, PREG_SET_ORDER);
                
                foreach ($matches as $match) {
                    $questionNumber = isset($match[1]) ? (int)$match[1] : null;
                    $questionText = isset($match[2]) ? trim($match[2]) : '';
                    
                    if (!empty($questionText)) {
                        // Options aur answer identify karein
                        $options = $this->extractOptionsFromQuestion($questionText);
                        $answer = $this->extractAnswerFromQuestion($questionText);
                        
                        $questions[] = [
                            'number' => $questionNumber,
                            'text' => $this->cleanQuestionText($questionText),
                            'options' => $options,
                            'answer' => $answer,
                            'raw_text' => $questionText,
                        ];
                    }
                }
                
                // Agar questions mil gaye to break karein
                if (!empty($questions)) {
                    break;
                }
            }

            // Agar pattern-based extraction fail ho, to text-based extraction try karein
            if (empty($questions)) {
                $questions = $this->extractQuestionsFromText($fullText);
            }

        } catch (Exception $e) {
            Log::warning("Error extracting questions: " . $e->getMessage());
        }

        return $questions;
    }

    /**
     * Question text se options extract karein
     * Common patterns: "A)", "a)", "1)", "(A)", etc.
     */
    protected function extractOptionsFromQuestion(string $questionText): array
    {
        $options = [];
        
        // Option patterns
        $optionPatterns = [
            '/([A-E])[\.\)]\s*(.+?)(?=[A-E][\.\)]|$)/is',
            '/([a-e])[\.\)]\s*(.+?)(?=[a-e][\.\)]|$)/is',
            '/(\d+)[\.\)]\s*(.+?)(?=\d+[\.\)]|$)/is',
        ];

        foreach ($optionPatterns as $pattern) {
            preg_match_all($pattern, $questionText, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                $optionLabel = isset($match[1]) ? trim($match[1]) : '';
                $optionText = isset($match[2]) ? trim($match[2]) : '';
                
                if (!empty($optionText) && strlen($optionText) > 3) {
                    $options[] = [
                        'label' => $optionLabel,
                        'text' => $this->cleanText($optionText),
                    ];
                }
            }
            
            if (!empty($options)) {
                break;
            }
        }

        return $options;
    }

    /**
     * Question text se answer extract karein
     */
    protected function extractAnswerFromQuestion(string $questionText): ?string
    {
        // Answer patterns: "Answer: A", "Correct Answer: B", etc.
        $answerPatterns = [
            '/Answer[:\s]+([A-Ea-e])/i',
            '/Correct\s+Answer[:\s]+([A-Ea-e])/i',
            '/Ans[:\s]+([A-Ea-e])/i',
        ];

        foreach ($answerPatterns as $pattern) {
            if (preg_match($pattern, $questionText, $matches)) {
                return strtoupper(trim($matches[1]));
            }
        }

        return null;
    }

    /**
     * Question text ko clean karein (options aur answers remove karke)
     */
    protected function cleanQuestionText(string $text): string
    {
        // Options remove karein
        $text = preg_replace('/[A-Ea-e][\.\)]\s*.+?(?=[A-Ea-e][\.\)]|Answer|$)/is', '', $text);
        
        // Answer line remove karein
        $text = preg_replace('/Answer[:\s]+[A-Ea-e].*$/i', '', $text);
        $text = preg_replace('/Correct\s+Answer[:\s]+[A-Ea-e].*$/i', '', $text);
        
        return trim($text);
    }

    /**
     * Text ko clean karein
     */
    protected function cleanText(string $text): string
    {
        // Extra whitespace remove karein
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    /**
     * Alternative: Text-based question extraction
     */
    protected function extractQuestionsFromText(string $fullText): array
    {
        $questions = [];
        
        // Text ko lines mein split karein
        $lines = explode("\n", $fullText);
        $currentQuestion = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Question start identify karein
            if (preg_match('/^(Q\d+|Question\s*\d+|\d+[\.\)])/i', $line)) {
                if ($currentQuestion) {
                    $questions[] = $currentQuestion;
                }
                
                $currentQuestion = [
                    'text' => $line,
                    'options' => [],
                    'answer' => null,
                ];
            } elseif ($currentQuestion) {
                // Options ya answer add karein
                if (preg_match('/^[A-Ea-e][\.\)]/', $line)) {
                    $currentQuestion['options'][] = [
                        'label' => substr($line, 0, 1),
                        'text' => substr($line, 2),
                    ];
                } elseif (preg_match('/Answer[:\s]+([A-Ea-e])/i', $line, $matches)) {
                    $currentQuestion['answer'] = strtoupper($matches[1]);
                } else {
                    $currentQuestion['text'] .= ' ' . $line;
                }
            }
        }
        
        if ($currentQuestion) {
            $questions[] = $currentQuestion;
        }

        return $questions;
    }

    /**
     * Document se tables extract karein
     */
    protected function extractTables($document): array
    {
        $tables = [];
        
        try {
            $pages = $document->getPages();
            
            foreach ($pages as $page) {
                $pageTables = $page->getTables();
                
                if ($pageTables) {
                    foreach ($pageTables as $table) {
                        $tableData = [];
                        $rows = $table->getBodyRows();
                        
                        foreach ($rows as $row) {
                            $rowData = [];
                            $cells = $row->getCells();
                            
                            foreach ($cells as $cell) {
                                $cellText = $cell->getLayout() ? $cell->getLayout()->getTextAnchor()->getText() : '';
                                $rowData[] = trim($cellText);
                            }
                            
                            if (!empty($rowData)) {
                                $tableData[] = $rowData;
                            }
                        }
                        
                        if (!empty($tableData)) {
                            $tables[] = $tableData;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            Log::warning("Error extracting tables: " . $e->getMessage());
        }

        return $tables;
    }

    /**
     * Document se entities extract karein
     */
    protected function extractEntities($document): array
    {
        $entities = [];
        
        try {
            $documentEntities = $document->getEntities();
            
            if ($documentEntities) {
                foreach ($documentEntities as $entity) {
                    $entities[] = [
                        'type' => $entity->getType(),
                        'text' => $entity->getTextAnchor() ? $entity->getTextAnchor()->getText() : '',
                        'confidence' => $entity->getConfidence(),
                    ];
                }
            }
        } catch (Exception $e) {
            Log::warning("Error extracting entities: " . $e->getMessage());
        }

        return $entities;
    }

    /**
     * Cleanup - Client close karein
     */
    public function __destruct()
    {
        if (isset($this->client) && $this->client) {
            try {
                $this->client->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
    }
}


