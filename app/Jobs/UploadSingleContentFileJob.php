<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class UploadSingleContentFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $targetModelClass;
    protected $targetModelId;
    protected $filePaths;

    public function __construct($targetModelClass, $targetModelId, $filePaths)
    {
        $this->targetModelClass = $targetModelClass;
        $this->targetModelId = $targetModelId;
        $this->filePaths = $filePaths;
    }

    public function handle()
    {
        try {
            // Recreate the model instance
            $targetModel = new $this->targetModelClass;
            $targetModel = $targetModel->find($this->targetModelId);
            
            if (!$targetModel) {
                \Log::error("Target model not found: {$this->targetModelClass}#{$this->targetModelId}");
                return;
            }

            // Process file paths in chunks to avoid memory issues
            $chunks = array_chunk($this->filePaths, 3); // Process 3 files at a time
            
            foreach ($chunks as $chunkIndex => $chunk) {
                foreach ($chunk as $fileData) {
                    try {
                        \Log::info("Processing file: " . $fileData['original_name'] . " at path: " . $fileData['temp_path']);
                        
                        // Check if stored file still exists
                        if (!file_exists($fileData['temp_path'])) {
                            \Log::error("Temp file not found: " . $fileData['temp_path']);
                            \Log::error("Directory contents: " . json_encode(scandir(dirname($fileData['temp_path']))));
                            continue;
                        }

                        \Log::info("File exists, size: " . filesize($fileData['temp_path']) . " bytes");

                        // Upload from stored temp file to R2
                        $targetModel
                            ->addMedia($fileData['temp_path'])
                            ->usingName($fileData['original_name'])
                            ->toMediaCollection('content_upload');
                        
                        // Clean up temp file after successful upload
                        if (file_exists($fileData['temp_path'])) {
                            unlink($fileData['temp_path']);
                        }
                            
                        \Log::info("File uploaded successfully: " . $fileData['original_name']);
                    } catch (\Exception $e) {
                        \Log::error("Failed to upload file {$fileData['original_name']}: " . $e->getMessage());
                        
                        // Clean up temp file on error too
                        if (isset($fileData['temp_path']) && file_exists($fileData['temp_path'])) {
                            unlink($fileData['temp_path']);
                        }
                    }
                }
                
                // Sleep between chunks to reduce server load (except for last chunk)
                if ($chunkIndex < count($chunks) - 1) {
                    sleep(2); // 2 seconds pause between chunks
                }
            }
        } catch (\Exception $e) {
            \Log::error("Failed to process files: " . $e->getMessage());
            throw $e;
        }
    }
}
