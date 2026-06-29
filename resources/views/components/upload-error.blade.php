@props([
    'message',
    'context' => 'default',
])

@php
    $validation = \IvanBaric\Gallery\Support\GallerySettings::validationForContext((string) $context);
    $text = \IvanBaric\Gallery\Support\GalleryUploadValidation::friendlyMessage((string) $message, $validation);
@endphp

<p {{ $attributes->class('mt-3 text-sm text-red-600 dark:text-red-400') }}>
    {{ $text }}
</p>
