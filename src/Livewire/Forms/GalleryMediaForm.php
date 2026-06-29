<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Livewire\Forms;

use Livewire\Form;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

final class GalleryMediaForm extends Form
{
    public ?string $alt = '';

    public ?string $title = '';

    public ?string $caption = '';

    public ?string $description = '';

    public ?string $credit = '';

    public ?string $source_url = '';

    public ?string $license = '';

    public bool $is_decorative = false;

    public ?int $lock_version = null;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'alt' => ['nullable', 'string', 'max:180'],
            'title' => ['nullable', 'string', 'max:180'],
            'caption' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'credit' => ['nullable', 'string', 'max:180'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'license' => ['nullable', 'string', 'max:180'],
            'is_decorative' => ['boolean'],
            'lock_version' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'alt' => __('alt tekst'),
            'title' => __('naslov slike'),
            'caption' => __('opis uz sliku'),
            'description' => __('interni SEO opis'),
            'credit' => __('autor ili kredit'),
            'source_url' => __('izvor URL'),
            'license' => __('licenca'),
            'is_decorative' => __('dekorativna slika'),
        ];
    }

    public function fillFromMedia(SpatieMedia $media): void
    {
        $this->alt = (string) $media->getCustomProperty('alt', '');
        $this->title = (string) $media->getCustomProperty('title', $media->name);
        $this->caption = (string) $media->getCustomProperty('caption', '');
        $this->description = (string) $media->getCustomProperty('description', '');
        $this->credit = (string) $media->getCustomProperty('credit', '');
        $this->source_url = (string) $media->getCustomProperty('source_url', '');
        $this->license = (string) $media->getCustomProperty('license', '');
        $this->is_decorative = (bool) $media->getCustomProperty('is_decorative', false);
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [
            'alt' => $this->alt,
            'title' => $this->title,
            'caption' => $this->caption,
            'description' => $this->description,
            'credit' => $this->credit,
            'source_url' => $this->source_url,
            'license' => $this->license,
            'is_decorative' => $this->is_decorative,
            'lock_version' => $this->lock_version,
        ];
    }
}
