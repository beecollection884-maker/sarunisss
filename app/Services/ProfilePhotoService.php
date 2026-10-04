<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProfilePhotoService
{
    public function store(UploadedFile $photo, string $directory): string
    {
        return $photo->store($directory, 'public');
    }

    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    public function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
