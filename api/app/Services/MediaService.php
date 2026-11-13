<?php

namespace App\Services;

use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function store(UploadedFile $file, User $user, array $attributes = []): Media
    {
        $disk = (string) config('cms.media.disk', 'public');
        $directory = 'media/'.now()->format('Y/m');
        $extension = strtolower(
            $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin'
        );
        $storedName = Str::ulid()->toBase32().'.'.$extension;

        $path = $file->storeAs($directory, $storedName, $disk);

        if ($path === false) {
            throw new \RuntimeException('Unable to store the uploaded file.');
        }

        [$width, $height] = $this->dimensions($file);

        return Media::create([
            'user_id' => $user->getKey(),
            'disk' => $disk,
            'path' => $path,
            'filename' => $file->getClientOriginalName() ?: $storedName,
            'mime_type' => $file->getClientMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize() ?: 0,
            'width' => $width,
            'height' => $height,
            'title' => $attributes['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'alt_text' => $attributes['alt_text'] ?? null,
            'caption' => $attributes['caption'] ?? null,
            'meta' => null,
        ]);
    }

    public function delete(Media $media): bool
    {
        Storage::disk($media->disk)->delete($media->path);

        return (bool) $media->delete();
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    protected function dimensions(UploadedFile $file): array
    {
        if (! str_starts_with((string) $file->getClientMimeType(), 'image/')) {
            return [null, null];
        }

        $size = @getimagesize($file->getRealPath());

        return $size === false ? [null, null] : [(int) $size[0], (int) $size[1]];
    }
}
