<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Livewire\Forms;

use IvanBaric\Gallery\Models\Gallery;
use Livewire\Form;

final class GalleryForm extends Form
{
    public string $title = '';

    public ?string $description = null;

    public ?int $lock_version = null;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'lock_version' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'title' => __('naziv galerije'),
            'description' => __('opis galerije'),
        ];
    }

    public function fillFromModel(Gallery $gallery): void
    {
        $this->title = (string) ($gallery->title ?: $gallery->displayTitle());
        $this->description = $gallery->description;
        $this->lock_version = method_exists($gallery, 'getLockVersion') ? $gallery->getLockVersion() : (int) ($gallery->lock_version ?? 0);
    }

    /**
     * @return array{title: string, description: string|null, lock_version: int|null}
     */
    public function data(): array
    {
        return [
            'title' => trim($this->title),
            'description' => $this->description,
            'lock_version' => $this->lock_version,
        ];
    }

    public function resetForm(): void
    {
        $this->title = '';
        $this->description = null;
        $this->lock_version = null;
    }
}
