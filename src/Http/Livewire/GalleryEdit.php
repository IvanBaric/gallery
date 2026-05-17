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

    public string $deletePassword = '';

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

    public function deleteGallery(): void
    {
        $this->validate([
            'deletePassword' => ['required', 'string', 'current_password'],
        ], [
            'deletePassword.current_password' => __('Lozinka nije ispravna.'),
        ]);

        $galleryTitle = $this->gallery->displayTitle();

        $this->gallery->clearMediaCollection($this->gallery->collection_name);
        $this->gallery->delete();

        Flux::toast(
            heading: __('Galerija obrisana'),
            text: $galleryTitle,
            variant: 'success',
        );

        $this->redirectRoute('admin.galleries.index', navigate: true);
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
