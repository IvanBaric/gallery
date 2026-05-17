<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Http\Livewire;

use Flux\Flux;
use IvanBaric\Gallery\Models\Gallery;
use Livewire\Component;

class GalleryEdit extends Component
{
    public string $uuid;

    public Gallery $gallery;

    public string $title = '';

    public ?string $description = null;

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;
        $this->loadGallery();
    }

    public function saveMeta(): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->gallery->forceFill([
            'title' => $validated['title'],
            'description' => $validated['description'],
        ])->save();

        Flux::toast(
            heading: __('Galerija spremljena'),
            text: __('Podaci galerije su ažurirani.'),
            variant: 'success',
        );
    }

    public function render()
    {
        return view('gallery::livewire.edit')->title($this->gallery->displayTitle());
    }

    private function loadGallery(): void
    {
        $this->gallery = Gallery::query()
            ->forCurrentTenant()
            ->with(['media', 'featuredMedia', 'galleryable'])
            ->where('uuid', $this->uuid)
            ->firstOrFail();

        $this->title = (string) ($this->gallery->title ?: $this->gallery->displayTitle());
        $this->description = $this->gallery->description;
    }
}
