<?php
namespace App\Jobs;

use App\Models\Paper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class UploadPaperImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $paperId;
    protected $filePath;
    protected $originalName;
    protected $mimeType;

    public function __construct($paperId, $filePath, $originalName, $mimeType)
    {
        $this->paperId = $paperId;
        $this->filePath = $filePath;
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
    }

    public function handle()
    {
        try {
            $paper = Paper::find($this->paperId);
            
            if (!$paper) {
                Log::error("Paper not found for image upload. Paper ID: {$this->paperId}");
                return;
            }
            
            if (!Storage::disk('local')->exists($this->filePath)) {
                Log::error("Temporary image file not found. Path: {$this->filePath}, Paper ID: {$this->paperId}");
                return;
            }
            
            Log::info("Starting image upload to R2 for paper ID: {$this->paperId}", [
                'temp_path' => $this->filePath,
                'original_name' => $this->originalName
            ]);
            
            // Add media from local temp file - it will automatically upload to R2
            $media = $paper->addMediaFromDisk($this->filePath, 'local')
                   ->usingName($this->originalName)
                   ->toMediaCollection('paper_image');
            
            // Clean up temporary local file
            Storage::disk('local')->delete($this->filePath);
            
            Log::info("Successfully uploaded image to R2 for paper ID: {$this->paperId}", [
                'media_id' => $media->id,
                'r2_url' => $paper->getFirstMediaUrl('paper_image')
            ]);
        } catch (Exception $e) {
            Log::error("Failed to upload paper image to R2. Paper ID: {$this->paperId}, Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}");
            
            // Clean up temp file even on error
            if (Storage::disk('local')->exists($this->filePath)) {
                Storage::disk('local')->delete($this->filePath);
            }
            
            // Re-throw to trigger job retry mechanism
            throw $e;
        }
    }
}







