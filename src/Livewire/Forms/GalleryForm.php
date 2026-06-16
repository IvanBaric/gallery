<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Livewire\Forms;

use IvanBaric\Gallery\Models\Gallery;
use Livewire\Form;

final class GalleryForm extends Form
{
    public string $title = '';

    public ?string $description = null;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
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
    }

    /**
     * @return array{title: string, description: string|null}
     */
    public function data(): array
    {
        return [
            'title' => trim($this->title),
            'description' => $this->description,
        ];
    }

    public function resetForm(): void
    {
        $this->title = '';
        $this->description = null;
    }
}
