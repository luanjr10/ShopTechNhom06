<?php

namespace App\Services;

use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Support\Str;

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

    /**
     * Tải lên tệp bất kỳ (PDF, Word, Excel, txt, zip...) dưới dạng tệp thô, giữ đúng tên và đuôi gốc.
     */
    public function uploadFile($file, string $folder = 'chat-shoptech'): string
    {
        // PHP lưu tệp upload dưới tên *.tmp; Cloudinary sẽ nối đuôi đó vào URL, nên upload từ một bản sao đặt đúng đuôi.
        $extension = strtolower(preg_replace('/[^A-Za-z0-9]/', '', (string) $file->getClientOriginalExtension()));
        $copy = sys_get_temp_dir().DIRECTORY_SEPARATOR.Str::random(24).($extension !== '' ? '.'.$extension : '');

        copy($file->getRealPath(), $copy);

        try {
            $result = (new UploadApi)->upload($copy, [
                'asset_folder' => $folder,
                'resource_type' => 'raw',
                'use_filename' => true,
                'unique_filename' => true,
            ]);
        } finally {
            @unlink($copy);
        }

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
