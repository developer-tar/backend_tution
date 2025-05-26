<?php

namespace App\MediaLibrary\PathGenerator;

use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SingleFolderPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $media->collection_name . '/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $media->collection_name . '/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $media->collection_name . '/responsive-images/';
    }
}
