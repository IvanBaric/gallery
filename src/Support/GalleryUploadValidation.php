<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Support;

final class GalleryUploadValidation
{
    /**
     * @param  array{max_files: int, max_file_size_kb: int, mimes: array<int, string>, min_width: int|null, min_height: int|null}  $validation
     * @return array<string, mixed>
     */
    public static function rules(array $validation, int $remainingSlots): array
    {
        return [
            'uploads' => ['array', 'max:'.$remainingSlots],
            'uploads.*' => self::imageRules($validation),
        ];
    }

    /**
     * @param  array{max_files: int, max_file_size_kb: int, mimes: array<int, string>, min_width: int|null, min_height: int|null}  $validation
     * @return array<string, mixed>
     */
    public static function singleRules(array $validation, string $field = 'queuedUpload'): array
    {
        return [
            $field => self::imageRules($validation),
        ];
    }

    /**
     * @param  array{max_files: int, max_file_size_kb: int, mimes: array<int, string>, min_width: int|null, min_height: int|null}  $validation
     * @return array<string, string>
     */
    public static function messages(array $validation, int $remainingSlots): array
    {
        return [
            'uploads.array' => __('Odaberite jednu ili više fotografija.'),
            'uploads.max' => trans_choice(
                '{1} Možete dodati još samo jednu fotografiju.|[2,*] Možete dodati najviše :count fotografija.',
                $remainingSlots,
                ['count' => $remainingSlots],
            ),
            'uploads.uploaded' => self::failedUploadMessage($validation),
            'uploads.*.uploaded' => self::failedUploadMessage($validation),
            'queuedUpload.uploaded' => self::failedUploadMessage($validation),
            'uploads.*.image' => __('Svaka datoteka mora biti slika.'),
            'queuedUpload.image' => __('Datoteka mora biti slika.'),
            'uploads.*.max' => __('Svaka fotografija mora biti manja od :max MB.', ['max' => self::maxFileSizeMb($validation)]),
            'queuedUpload.max' => __('Fotografija mora biti manja od :max MB.', ['max' => self::maxFileSizeMb($validation)]),
            'uploads.*.mimes' => __('Dopušteni formati su: :values.', ['values' => self::mimeList($validation)]),
            'queuedUpload.mimes' => __('Dopušteni formati su: :values.', ['values' => self::mimeList($validation)]),
            'uploads.*.dimensions' => __('Fotografija ne zadovoljava minimalne dimenzije.'),
            'queuedUpload.dimensions' => __('Fotografija ne zadovoljava minimalne dimenzije.'),
        ];
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return [
            'uploads' => __('fotografije'),
            'uploads.*' => __('fotografija'),
            'queuedUpload' => __('fotografija'),
        ];
    }

    /**
     * @param  array{max_files: int, max_file_size_kb: int, mimes: array<int, string>, min_width: int|null, min_height: int|null}  $validation
     */
    public static function friendlyMessage(string $message, array $validation): string
    {
        if (! self::isGenericUploadFailure($message)) {
            return $message;
        }

        return self::failedUploadMessage($validation);
    }

    /**
     * @param  array{max_files: int, max_file_size_kb: int, mimes: array<int, string>, min_width: int|null, min_height: int|null}  $validation
     */
    public static function failedUploadMessage(array $validation): string
    {
        return __('Fotografije se nisu mogle prenijeti. Provjerite veličinu i format slika pa pokušajte ponovno. Dopušteno je do :size MB po fotografiji (:formats).', [
            'size' => self::maxFileSizeMb($validation),
            'formats' => self::mimeList($validation),
        ]);
    }

    /**
     * @param  array{max_files: int, max_file_size_kb: int, mimes: array<int, string>, min_width: int|null, min_height: int|null}  $validation
     */
    public static function maxFileSizeMb(array $validation): string
    {
        $mb = ((int) $validation['max_file_size_kb']) / 1024;

        return floor($mb) === $mb
            ? (string) (int) $mb
            : rtrim(rtrim(number_format($mb, 1, ',', ''), '0'), ',');
    }

    /**
     * @param  array{max_files: int, max_file_size_kb: int, mimes: array<int, string>, min_width: int|null, min_height: int|null}  $validation
     */
    public static function mimeList(array $validation): string
    {
        return strtoupper(implode(', ', $validation['mimes']));
    }

    private static function isGenericUploadFailure(string $message): bool
    {
        $message = str($message)->lower()->toString();

        return str_contains($message, 'failed to upload')
            || str_contains($message, 'prijenos polja')
            || str_contains($message, 'nije uspio')
            || str_contains($message, 'uploads.')
            || str_contains($message, 'files.');
    }

    /**
     * @param  array{max_files: int, max_file_size_kb: int, mimes: array<int, string>, min_width: int|null, min_height: int|null}  $validation
     * @return array<int, string>
     */
    private static function imageRules(array $validation): array
    {
        $imageRules = ['image', 'max:'.$validation['max_file_size_kb']];

        if ($validation['mimes'] !== []) {
            $imageRules[] = 'mimes:'.implode(',', $validation['mimes']);
        }

        if ($validation['min_width']) {
            $imageRules[] = 'dimensions:min_width='.$validation['min_width'];
        }

        if ($validation['min_height']) {
            $imageRules[] = 'dimensions:min_height='.$validation['min_height'];
        }

        return $imageRules;
    }
}
