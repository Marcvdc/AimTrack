<?php

namespace App\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Throwable;

class StoragePathInitializer
{
    /**
     * Ensure required storage subdirectories exist: the framework caches and the
     * roots of the local filesystem disks (#159).
     *
     * A directory that cannot be created is logged, not thrown. This runs on every
     * boot, so an exception here would turn every request into a 500, including
     * /health, which is exactly where storage_unwritable should be reported. The
     * Docker entrypoint already fails hard at boot for the production stack.
     */
    public static function ensure(): void
    {
        /** @var Filesystem $filesystem */
        $filesystem = app(Filesystem::class);

        foreach (self::directories() as $directory) {
            if ($filesystem->isDirectory($directory)) {
                continue;
            }

            try {
                $created = $filesystem->makeDirectory($directory, 0775, true);
            } catch (Throwable) {
                $created = false;
            }

            if (! $created && ! $filesystem->isDirectory($directory)) {
                Log::warning('Storage-map kon niet worden aangemaakt', ['path' => $directory]);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private static function directories(): array
    {
        return array_values(array_filter([
            storage_path('framework/cache'),
            storage_path('framework/cache/data'),
            storage_path('framework/views'),
            storage_path('framework/sessions'),
            self::localDiskRoot((string) config('filesystems.default')),
            self::localDiskRoot('public'),
        ]));
    }

    /**
     * The root of a disk that lives on the local filesystem, or null for a
     * disk on another driver (such as s3) or a disk that is not configured.
     */
    private static function localDiskRoot(string $disk): ?string
    {
        $config = config("filesystems.disks.{$disk}");

        if (! is_array($config) || ($config['driver'] ?? null) !== 'local' || blank($config['root'] ?? null)) {
            return null;
        }

        return (string) $config['root'];
    }
}
