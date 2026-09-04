<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Pictures for products and their flavours.
 *
 * Files live on the public disk under one folder, so a stored path is always
 * ours to delete. A path that is already a URL was typed in by hand and points
 * somewhere we do not own, so it is never touched.
 */
class ProductImage
{
    public const DISK = 'public';

    public const DIR = 'products';

    /** The formats worth accepting, and how large a phone photo may be. */
    public const RULES = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];

    /** Store an upload, discarding whatever it replaces. */
    public static function put(UploadedFile $file, ?string $replacing = null): string
    {
        self::forget($replacing);

        return $file->store(self::DIR, self::DISK);
    }

    /** Delete a stored file, ignoring anything we did not put there. */
    public static function forget(?string $path): void
    {
        if (! self::isStored($path)) {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }

    /** The address a browser can load, whether we stored it or it was typed. */
    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http')
            ? $path
            : Storage::disk(self::DISK)->url($path);
    }

    private static function isStored(?string $path): bool
    {
        return $path
            && ! str_starts_with($path, 'http')
            && str_starts_with($path, self::DIR.'/');
    }
}
