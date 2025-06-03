<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UploadSingleContentFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Model $targetModel;
    protected UploadedFile $file;

    public function __construct(Model $targetModel, UploadedFile $file)
    {
        $this->targetModel = $targetModel;
        $this->file = $file;
    }

    public function handle()
    {
        $this->targetModel
            ->addMedia($this->file)
            ->toMediaCollection('content_upload', 'public');
    }
}
