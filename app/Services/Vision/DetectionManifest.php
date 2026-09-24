<?php

namespace App\Services\Vision;

use App\Enums\TargetType;
use ValueError;

/**
 * Leest de gelabelde meetset in.
 *
 * Formaat:
 * {
 *   "photos_dir": "fixtures/photos",
 *   "photos": [
 *     {"file": "IMG_5672.jpg", "target_type": "kkp_25m", "expected_shot_count": 5,
 *      "truth_rings": [8, 8, 7, 7, 6]}
 *   ]
 * }
 *
 * "truth" met objecten die een "ring" bevatten wordt ook geaccepteerd, zodat het
 * manifest uit de Python-service zonder omzetten bruikbaar is. Ontbreekt de
 * grondwaarheid, dan draait de meting wel maar blijft de ringnauwkeurigheid leeg.
 */
class DetectionManifest
{
    /**
     * @return list<array<string, mixed>>
     *
     * @throws VisionException
     */
    public function load(string $manifestPath): array
    {
        if (! is_readable($manifestPath)) {
            throw new VisionException("Manifest niet leesbaar: {$manifestPath}");
        }

        $decoded = json_decode((string) file_get_contents($manifestPath), true);

        if (! is_array($decoded)) {
            throw new VisionException("Manifest bevat geen geldige JSON: {$manifestPath}");
        }

        $photos = $decoded['photos'] ?? null;

        if (! is_array($photos) || $photos === []) {
            throw new VisionException("Manifest bevat geen 'photos'-lijst: {$manifestPath}");
        }

        $baseDirectory = $this->baseDirectory($manifestPath, $decoded['photos_dir'] ?? null);

        return array_values(array_map(
            fn (array $photo): array => $this->entry($photo, $baseDirectory),
            array_filter($photos, 'is_array'),
        ));
    }

    private function baseDirectory(string $manifestPath, mixed $photosDir): string
    {
        $manifestDirectory = dirname($manifestPath);

        if (! is_string($photosDir) || $photosDir === '') {
            return $manifestDirectory;
        }

        return str_starts_with($photosDir, '/')
            ? rtrim($photosDir, '/')
            : rtrim($manifestDirectory.'/'.$photosDir, '/');
    }

    /**
     * @param  array<string, mixed>  $photo
     * @return array<string, mixed>
     *
     * @throws VisionException
     */
    private function entry(array $photo, string $baseDirectory): array
    {
        $file = $photo['file'] ?? null;

        if (! is_string($file) || $file === '') {
            throw new VisionException("Manifest-regel zonder 'file'.");
        }

        $path = str_starts_with($file, '/') ? $file : $baseDirectory.'/'.$file;

        if (! is_readable($path)) {
            throw new VisionException("Foto uit het manifest niet gevonden: {$path}");
        }

        try {
            $targetType = TargetType::from((string) ($photo['target_type'] ?? ''));
        } catch (ValueError) {
            throw new VisionException(sprintf(
                "Onbekend roostype '%s' bij %s. Geldig: %s.",
                (string) ($photo['target_type'] ?? ''),
                $file,
                implode(', ', array_column(TargetType::cases(), 'value')),
            ));
        }

        return [
            'path' => $path,
            'target_type' => $targetType,
            'expected_shot_count' => isset($photo['expected_shot_count'])
                ? (int) $photo['expected_shot_count']
                : null,
            'truth_rings' => $this->truthRings($photo),
        ];
    }

    /**
     * @param  array<string, mixed>  $photo
     * @return list<int>|null
     */
    private function truthRings(array $photo): ?array
    {
        if (isset($photo['truth_rings']) && is_array($photo['truth_rings'])) {
            return array_values(array_map('intval', $photo['truth_rings']));
        }

        if (isset($photo['truth']) && is_array($photo['truth'])) {
            return array_values(array_map(
                static fn (mixed $shot): int => is_array($shot) ? (int) ($shot['ring'] ?? 0) : (int) $shot,
                $photo['truth'],
            ));
        }

        return null;
    }
}
