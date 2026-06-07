<?php

namespace App\Support;

use Illuminate\Filesystem\Filesystem;

class StoragePathInitializer
{
    /**
     * Ensure required storage subdirectories exist for framework caches.
     */
    public static function ensure(): void
    {
        /** @var Filesystem $filesystem */
        $filesystem = app(Filesystem::class);

        foreach (self::directories() as $directory) {
            if ($filesystem->isDirectory($directory)) {
                continue;
            }

            $filesystem->makeDirectory($directory, 0775, true);
        }
    }

    /**
     * @return array<int, string>
     */
    private static function directories(): array
    {
        return [
            storage_path('framework/cache'),
            storage_path('framework/cache/data'),
            storage_path('framework/views'),
            storage_path('framework/sessions'),
        ];
    }
}
