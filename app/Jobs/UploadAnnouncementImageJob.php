<?php

namespace App\Jobs;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class UploadAnnouncementImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $announcementId;
    protected $filePath;
    protected $originalName;
    protected $mimeType;

    public function __construct($announcementId, $filePath, $originalName, $mimeType)
    {
        $this->announcementId = $announcementId;
        $this->filePath = $filePath;
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
    }

    public function handle()
    {
        try {
            $announcement = Announcement::find($this->announcementId);

            if (!$announcement) {
                Log::error("Announcement not found for image upload. Announcement ID: {$this->announcementId}");
                return;
            }

            if (!Storage::disk('local')->exists($this->filePath)) {
                Log::error("Temporary image file not found. Path: {$this->filePath}, Announcement ID: {$this->announcementId}");
                return;
            }

            Log::info("Starting image upload to R2 for announcement ID: {$this->announcementId}", [
                'temp_path' => $this->filePath,
                'original_name' => $this->originalName
            ]);

            // Add media from local temp file - it will automatically upload to R2
            $media = $announcement->addMediaFromDisk($this->filePath, 'local')
                ->usingName($this->originalName)
                ->toMediaCollection('announcement_image');

            // Clean up temporary local file
            Storage::disk('local')->delete($this->filePath);

            Log::info("Successfully uploaded image to R2 for announcement ID: {$this->announcementId}", [
                'media_id' => $media->id,
                'r2_url' => $announcement->getFirstMediaUrl('announcement_image')
            ]);
        } catch (Exception $e) {
            Log::error("Failed to upload announcement image to R2. Announcement ID: {$this->announcementId}, Message => {$e->getMessage()}, File => {$e->getFile()}, Line => {$e->getLine()}");

            // Clean up temp file even on error
            if (Storage::disk('local')->exists($this->filePath)) {
                Storage::disk('local')->delete($this->filePath);
            }

            // Re-throw to trigger job retry mechanism
            throw $e;
        }
    }
}
