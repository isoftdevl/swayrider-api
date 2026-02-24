<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class ImageUploadService
{
    /**
     * Upload an image (base64 or file) and return the public URL.
     *
     * @param mixed $imageContent
     * @param string $path
     * @param int|null $width
     * @param int|null $height
     * @return string
     */
    public function upload($imageContent, $path, $width = 800, $height = null)
    {
        // Use direct_public disk which points to public/images
        $filename = Str::random(40) . '.jpg';
        $fullPath = $path . '/' . $filename;

        // Process image with Intervention
        $img = Image::read($imageContent);
        
        if ($width) {
            $img->scale(width: $width, height: $height);
        }

        // Encode as jpg
        $encoded = $img->toJpeg(80);

        // Store to direct_public disk
        Storage::disk('direct_public')->put($fullPath, (string) $encoded);

        return Storage::disk('direct_public')->url($fullPath);
    }

    /**
     * Delete an image from storage using its public URL.
     *
     * @param string $url
     * @return bool
     */
    public function delete($url)
    {
        $baseUrl = Storage::disk('direct_public')->url('');
        $path = str_replace($baseUrl, '', $url);
        
        if (Storage::disk('direct_public')->exists($path)) {
            return Storage::disk('direct_public')->delete($path);
        }
        return false;
    }
}
