<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageService
{
    /**
     * Convert base64 string to image file and store it
     *
     * @param string $base64String The base64 encoded image string
     * @param string $folder The folder to store the image (e.g., 'profile_pictures', 'cover_images')
     * @param string|null $oldImagePath Path to old image to delete (optional)
     * @return string|null The path to the stored image, or null if conversion failed
     */
    public function convertBase64ToImage(string $base64String, string $folder = 'images', ?string $oldImagePath = null): ?string
    {
        try {
            // Remove data URL prefix if present (e.g., "data:image/png;base64,")
            if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $matches)) {
                $imageType = $matches[1];
                $base64String = preg_replace('/^data:image\/\w+;base64,/', '', $base64String);
            } else {
                // Try to detect image type from base64 string
                $imageType = 'png'; // default
            }

            // Decode base64 string
            $imageData = base64_decode($base64String, true);

            if ($imageData === false) {
                throw new \Exception('Invalid base64 string');
            }

            // Validate that it's actually an image
            $imageInfo = @getimagesizefromstring($imageData);
            if ($imageInfo === false) {
                throw new \Exception('Invalid image data');
            }

            // Determine file extension from image type
            $allowedTypes = ['jpeg', 'jpg', 'png', 'gif', 'webp'];
            $extension = in_array(strtolower($imageType), $allowedTypes) ? strtolower($imageType) : 'png';

            // Generate unique filename
            $filename = Str::random(40) . '.' . $extension;
            $path = $folder . '/' . $filename;

            // Store the image
            Storage::disk('public')->put($path, $imageData);

            // Delete old image if provided
            if ($oldImagePath && Storage::disk('public')->exists($oldImagePath)) {
                Storage::disk('public')->delete($oldImagePath);
            }

            return $path;
        } catch (\Exception $e) {
            \Log::error('ImageService: Failed to convert base64 to image', [
                'error' => $e->getMessage(),
                'folder' => $folder
            ]);
            return null;
        }
    }

    /**
     * Check if a string is a valid base64 image
     *
     * @param string $base64String
     * @return bool
     */
    public function isValidBase64Image(string $base64String): bool
    {
        try {
            // Remove data URL prefix if present
            $base64String = preg_replace('/^data:image\/\w+;base64,/', '', $base64String);
            
            // Decode base64 string
            $imageData = base64_decode($base64String, true);
            
            if ($imageData === false) {
                return false;
            }

            // Validate that it's actually an image
            return @getimagesizefromstring($imageData) !== false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
