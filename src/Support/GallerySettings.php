<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Support;

use Illuminate\Support\Facades\Schema;
use IvanBaric\Gallery\Models\GallerySetting;

final class GallerySettings
{
    /**
     * @return array<string, array{label: string, width: int|null, height: int|null, fit: string, enabled: bool}>
     */
    public static function imageSizes(): array
    {
        $defaults = (array) config('gallery.sizes', []);
        $stored = self::stored('image_sizes', []);

        return collect(array_replace_recursive($defaults, is_array($stored) ? $stored : []))
            ->mapWithKeys(function (array $size, string $name): array {
                return [$name => self::normalizeSize($name, $size)];
            })
            ->all();
    }

    /**
     * @return array{max_files: int, max_file_size_kb: int, mimes: array<int, string>, min_width: int|null, min_height: int|null}
     */
    public static function validationForContext(string $context): array
    {
        $base = (array) config('gallery.validation', []);
        $contextRules = (array) config("gallery.contexts.$context", []);
        $policy = corexis_image_upload();

        return [
            'max_files' => self::positiveInteger($contextRules['max_files'] ?? $base['max_files'] ?? 30, 30),
            'max_file_size_kb' => self::positiveInteger($contextRules['max_file_size_kb'] ?? $base['max_file_size_kb'] ?? null, $policy->maxFileSizeKb()),
            'mimes' => self::stringList($contextRules['mimes'] ?? $base['mimes'] ?? null, $policy->mimes()),
            'min_width' => self::nullablePositiveInteger($contextRules['min_width'] ?? $base['min_width'] ?? $policy->minWidth()),
            'min_height' => self::nullablePositiveInteger($contextRules['min_height'] ?? $base['min_height'] ?? $policy->minHeight()),
        ];
    }

    public static function put(string $key, mixed $value): void
    {
        if (! self::settingsTableExists()) {
            return;
        }

        GallerySetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function stored(string $key, mixed $default = null): mixed
    {
        if (! self::settingsTableExists()) {
            return $default;
        }

        return GallerySetting::query()->where('key', $key)->first()?->value ?? $default;
    }

    /**
     * @param  array<string, mixed>  $size
     * @return array{label: string, width: int|null, height: int|null, fit: string, enabled: bool}
     */
    public static function normalizeSize(string $name, array $size): array
    {
        $fit = (string) ($size['fit'] ?? 'contain');

        return [
            'label' => (string) ($size['label'] ?? str($name)->headline()),
            'width' => self::nullablePositiveInteger($size['width'] ?? null),
            'height' => self::nullablePositiveInteger($size['height'] ?? null),
            'fit' => in_array($fit, ['crop', 'contain'], true) ? $fit : 'contain',
            'enabled' => (bool) ($size['enabled'] ?? true),
        ];
    }

    private static function nullablePositiveInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    private static function positiveInteger(mixed $value, int $fallback): int
    {
        if (! is_numeric($value)) {
            return max(1, $fallback);
        }

        return max(1, (int) $value);
    }

    /**
     * @param  array<int, string>  $fallback
     * @return array<int, string>
     */
    private static function stringList(mixed $value, array $fallback): array
    {
        $items = is_array($value) ? $value : $fallback;

        return array_values(array_filter($items, static fn (mixed $item): bool => is_string($item) && $item !== ''));
    }

    private static function settingsTableExists(): bool
    {
        try {
            return Schema::hasTable((string) config('gallery.tables.settings', 'gallery_settings'));
        } catch (\Throwable) {
            return false;
        }
    }
}
