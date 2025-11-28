<?php
namespace App\Jobs;

use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class UploadCourseImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $courseId;
    protected $filePath;
    protected $originalName;
    protected $mimeType;

    public function __construct($courseId, $filePath, $originalName, $mimeType)
    {
        $this->courseId = $courseId;
        $this->filePath = $filePath;
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
    }

    public function handle()
    {
        try {
            $course = Course::find($this->courseId);
            
            if (!$course) {
                Log::error("Course not found for image upload. Course ID: {$this->courseId}");
                return;
            }
            
            if (!Storage::disk('local')->exists($this->filePath)) {
                Log::error("Temporary image file not found. Path: {$this->filePath}, Course ID: {$this->courseId}");
                return;
            }
            
            Log::info("Starting image upload to R2 for course ID: {$this->courseId}", [
                'temp_path' => $this->filePath,
                'original_name' => $this->originalName
            ]);
            
            // Add media from local temp file - it will automatically upload to R2
            $media = $course->addMediaFromDisk($this->filePath, 'local')
                   ->usingName($this->originalName)
                   ->toMediaCollection('course_image');
            
            // Clean up temporary local file
            Storage::disk('local')->delete($this->filePath);
            
            Log::info("Successfully uploaded image to R2 for course ID: {$this->courseId}", [
                'media_id' => $media->id,
                'r2_url' => $course->getFirstMediaUrl('course_image')
            ]);
        } catch (Exception $e) {
            Log::error("Failed to upload course image to R2. Course ID: {$this->courseId}, Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}");
            
            // Clean up temp file even on error
            if (Storage::disk('local')->exists($this->filePath)) {
                Storage::disk('local')->delete($this->filePath);
            }
            
            // Re-throw to trigger job retry mechanism
            throw $e;
        }
    }
}
