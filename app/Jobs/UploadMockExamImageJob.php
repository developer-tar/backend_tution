<?php
namespace App\Jobs;

use App\Models\MockExam;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class UploadMockExamImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $mockExamId;
    protected $filePath;
    protected $originalName;
    protected $mimeType;

    public function __construct($mockExamId, $filePath, $originalName, $mimeType)
    {
        $this->mockExamId = $mockExamId;
        $this->filePath = $filePath;
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
    }

    public function handle()
    {
        try {
            $mockExam = MockExam::find($this->mockExamId);
            
            if (!$mockExam) {
                Log::error("Mock exam not found for image upload. Mock Exam ID: {$this->mockExamId}");
                return;
            }
            
            if (!Storage::disk('local')->exists($this->filePath)) {
                Log::error("Temporary image file not found. Path: {$this->filePath}, Mock Exam ID: {$this->mockExamId}");
                return;
            }
            
            Log::info("Starting image upload to R2 for mock exam ID: {$this->mockExamId}", [
                'temp_path' => $this->filePath,
                'original_name' => $this->originalName
            ]);
            
            // Add media from local temp file - it will automatically upload to R2
            $media = $mockExam->addMediaFromDisk($this->filePath, 'local')
                   ->usingName($this->originalName)
                   ->toMediaCollection('mock_exam_image');
            
            // Clean up temporary local file
            Storage::disk('local')->delete($this->filePath);
            
            Log::info("Successfully uploaded image to R2 for mock exam ID: {$this->mockExamId}", [
                'media_id' => $media->id,
                'r2_url' => $mockExam->getFirstMediaUrl('mock_exam_image')
            ]);
        } catch (Exception $e) {
            Log::error("Failed to upload mock exam image to R2. Mock Exam ID: {$this->mockExamId}, Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}");
            
            // Clean up temp file even on error
            if (Storage::disk('local')->exists($this->filePath)) {
                Storage::disk('local')->delete($this->filePath);
            }
            
            // Re-throw to trigger job retry mechanism
            throw $e;
        }
    }
}
