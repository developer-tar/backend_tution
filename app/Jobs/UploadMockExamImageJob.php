<?php
namespace App\Jobs;

use App\Models\Course;
use App\Models\MockExam;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\UploadedFile;

class UploadMockExamImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $mockExam;
    protected $image;

    public function __construct(MockExam $mockExam, UploadedFile $image)
    {
        $this->mockExam = $mockExam;
        $this->image = $image;
    }

    public function handle()
    {
        $this->mockExam->addMedia($this->image)->toMediaCollection('mock_exam_image');
    }
}
