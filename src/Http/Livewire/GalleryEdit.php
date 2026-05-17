<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Http\Livewire;

use Flux\Flux;
use IvanBaric\Gallery\Jobs\RegenerateGalleryConversions;
use IvanBaric\Gallery\Models\Gallery;
use IvanBaric\Gallery\Support\GalleryPermissions;
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
        $this->authorizeGalleryAction('view');
        $this->uuid = $uuid;
        $this->loadGallery();
    }

    public function openMetaModal(): void
    {
        $this->authorizeGalleryAction('update');
        $this->dispatch('modal-show', name: 'gallery-meta-edit');
    }

    public function openRegenerateConfirmation(): void
    {
        $this->authorizeGalleryAction('regenerate');
        $this->dispatch('modal-show', name: 'gallery-regenerate-confirm');
    }

    public function openDeleteConfirmation(): void
    {
        $this->authorizeGalleryAction('delete');
        $this->deletePassword = '';
        $this->resetValidation('deletePassword');
        $this->dispatch('modal-show', name: 'gallery-delete-confirm');
    }

    public function saveMeta(): void
    {
        $this->authorizeGalleryAction('update');

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

    public function regenerateGallery(): void
    {
        $this->authorizeGalleryAction('regenerate');

        $ids = $this->gallery
            ->getMedia($this->gallery->collection_name)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($ids === []) {
            $this->dispatch('modal-close', name: 'gallery-regenerate-confirm');
            Flux::toast(text: __('Galerija nema fotografija za regeneriranje.'), variant: 'warning');

            return;
        }

        $this->gallery->markRegenerationQueued(count($ids));
        RegenerateGalleryConversions::dispatch((int) $this->gallery->getKey(), $ids);
        $this->gallery->refresh();
        $this->dispatch('modal-close', name: 'gallery-regenerate-confirm');

        Flux::toast(
            heading: __('Regeneriranje pokrenuto'),
            text: __('Galerija je poslana u obradu. Možete nastaviti raditi dok se veličine generiraju.'),
            variant: 'success',
        );
    }

    public function deleteGallery(): void
    {
        $this->authorizeGalleryAction('delete');

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

    public function allowsGalleryAction(string $action): bool
    {
        return GalleryPermissions::allows(auth()->user(), $action);
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

    private function authorizeGalleryAction(string $action): void
    {
        GalleryPermissions::authorize($action);
    }
}
