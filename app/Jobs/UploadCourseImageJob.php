<?php
namespace App\Jobs;

use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\UploadedFile;

class UploadCourseImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $course;
    protected $image;

    public function __construct(Course $course, UploadedFile $image)
    {
        $this->course = $course;
        $this->image = $image;
    }

    public function handle()
    {
        $this->course->addMedia($this->image)->toMediaCollection('course_image');
    }
}
