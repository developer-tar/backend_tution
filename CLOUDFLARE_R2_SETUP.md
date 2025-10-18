# Cloudflare R2 Setup Guide

## Overview
This application has been migrated from AWS S3 to Cloudflare R2 for file storage. The `UploadCourseImageJob` in `CourseController.php` (lines 155-157) now uses Cloudflare R2 instead of AWS S3.

## Environment Configuration

### Required Environment Variables
Add these variables to your `.env` file:

#### Option 1: Account Level Credentials (Recommended)
```env
# Cloudflare R2 Configuration (Account Level)
CLOUDFLARE_R2_ACCESS_KEY_ID=0ed8d789a9754db6dfcdb21dfcb830fe
CLOUDFLARE_R2_SECRET_ACCESS_KEY=67246bfea6dca933015556fe0ff2abfcb95083ef71f920b8e0d37fee2babdfc8
CLOUDFLARE_R2_REGION=auto
CLOUDFLARE_R2_BUCKET=your-bucket-name
CLOUDFLARE_R2_ENDPOINT=https://5cdf0983ec481cd7db01e38d022b7790.r2.cloudflarestorage.com
CLOUDFLARE_R2_URL=https://your-bucket-name.5cdf0983ec481cd7db01e38d022b7790.r2.cloudflarestorage.com

# Set media disk to use R2
MEDIA_DISK=r2
```

#### Option 2: User Token Credentials (Alternative)
```env
# Cloudflare R2 Configuration (User Token)
CLOUDFLARE_R2_ACCESS_KEY_ID=4c555200bb24d5c5df780e26515fa924
CLOUDFLARE_R2_SECRET_ACCESS_KEY=862fcc40fe14d1ed38717735b50e85afd2400551e762c0a475ca1de3e6bbb180
CLOUDFLARE_R2_REGION=auto
CLOUDFLARE_R2_BUCKET=your-bucket-name
CLOUDFLARE_R2_ENDPOINT=https://5cdf0983ec481cd7db01e38d022b7790.r2.cloudflarestorage.com
CLOUDFLARE_R2_URL=https://your-bucket-name.5cdf0983ec481cd7db01e38d022b7790.r2.cloudflarestorage.com

# Set media disk to use R2
MEDIA_DISK=r2
```

### Bucket Configuration
Make sure to replace `your-bucket-name` with your actual Cloudflare R2 bucket name in both:
- `CLOUDFLARE_R2_BUCKET`
- `CLOUDFLARE_R2_URL`

### Which Credentials to Use?
- **Account Level** (Option 1): Better for production, more permissions
- **User Token** (Option 2): More restricted, good for specific use cases

## Files Modified
1. **`.env.example`** - Added Cloudflare R2 configuration variables
2. **`config/filesystems.php`** - Added R2 disk configuration, commented out S3 config
3. **`config/media-library.php`** - Changed default disk from 's3' to 'r2'

## Code Changes
The existing code in `CourseController.php` remains unchanged:
```php
if ($request->hasFile('course_image')) {
    UploadCourseImageJob::dispatch($courseObj, $request->file('course_image'));
}
```

The `UploadCourseImageJob` automatically uses the configured media disk (now R2) through the Spatie Media Library package.

## Testing
1. Ensure your `.env` file has the correct R2 credentials
2. Create a course with an image upload
3. Verify the image is uploaded to your Cloudflare R2 bucket
4. Check that the image URL is accessible

## Rollback Instructions
If you need to rollback to AWS S3:
1. Uncomment the S3 configuration in `config/filesystems.php`
2. Comment out the R2 configuration
3. Set `MEDIA_DISK=s3` in your `.env` file
4. Add your AWS credentials back to the `.env` file
