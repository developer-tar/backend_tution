<?php
namespace App\Jobs;

use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

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
        $course = Course::find($this->courseId);
        
        if ($course && Storage::disk('local')->exists($this->filePath)) {
            // Add media from local temp file - it will automatically upload to R2
            $course->addMediaFromDisk($this->filePath, 'local')
                   ->usingName($this->originalName)
                   ->toMediaCollection('course_image');
            
            // Clean up temporary local file
            Storage::disk('local')->delete($this->filePath);
        }
    }
}
