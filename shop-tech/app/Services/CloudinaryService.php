<?php

namespace App\Services;

use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Http\UploadedFile;

class CloudinaryService
{
    public function uploadImage($file, string $folder = 'thumbnail-shoptech'): string
    {
        $result = (new UploadApi)->upload(
            $file->getRealPath(),
            [
                'asset_folder' => $folder,
            ]
        );

        return $result['secure_url'];
    }

    public function uploadMultipleImages(array $files, string $folder = 'thumbnail-shoptech'): array
    {
        return array_map(
            fn ($file) => $this->uploadImage($file, $folder),
            $files
        );
    }
}
