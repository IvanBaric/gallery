<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Http\Livewire;

use Flux\Flux;
use Illuminate\Support\Facades\Artisan;
use IvanBaric\Gallery\Models\Gallery;
use IvanBaric\Gallery\Support\GallerySettings;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class GalleryIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public ?string $activeGalleryUuid = null;

    public string $newGalleryTitle = '';

    public string $newGalleryCollection = 'images';

    public ?string $newGalleryDescription = null;

    public array $sizeSettings = [];

    public function mount(): void
    {
        $this->loadSizeSettings();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openSettings(): void
    {
        $this->loadSizeSettings();
        $this->dispatch('modal-show', name: 'gallery-settings');
    }

    public function openCreateGallery(): void
    {
        $this->resetValidation();
        $this->newGalleryTitle = '';
        $this->newGalleryCollection = 'images';
        $this->newGalleryDescription = null;
        $this->dispatch('modal-show', name: 'gallery-create');
    }

    public function createGallery(): void
    {
        $validated = $this->validate([
            'newGalleryTitle' => ['required', 'string', 'max:180'],
            'newGalleryCollection' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9._-]+$/'],
            'newGalleryDescription' => ['nullable', 'string', 'max:2000'],
        ]);

        $gallery = Gallery::query()->create([
            'title' => $validated['newGalleryTitle'],
            'collection_name' => $validated['newGalleryCollection'],
            'description' => $validated['newGalleryDescription'],
        ]);

        $this->dispatch('modal-close', name: 'gallery-create');
        $this->openGallery($gallery->uuid);

        Flux::toast(
            heading: __('Galerija kreirana'),
            text: __('Nova galerija je spremna za dodavanje fotografija.'),
            variant: 'success',
        );
    }

    public function openGallery(string $uuid): void
    {
        $exists = Gallery::query()
            ->forCurrentTenant()
            ->where('uuid', $uuid)
            ->exists();

        abort_unless($exists, 404);

        $this->activeGalleryUuid = $uuid;
        $this->dispatch('modal-show', name: 'gallery-open');
    }

    public function saveSettings(): void
    {
        $normalized = [];

        foreach ($this->sizeSettings as $name => $size) {
            $normalized[$name] = GallerySettings::normalizeSize((string) $name, (array) $size);
        }

        GallerySettings::put('image_sizes', $normalized);
        $this->sizeSettings = $normalized;
        $this->dispatch('modal-close', name: 'gallery-settings');

        Flux::toast(
            heading: __('Postavke galerije spremljene'),
            text: __('Pokrenite regeneriranje ako želite primijeniti nove veličine na postojeće slike.'),
            variant: 'success',
        );
    }

    public function regenerateGallery(string $uuid): void
    {
        $gallery = Gallery::query()->forCurrentTenant()->where('uuid', $uuid)->firstOrFail();
        $ids = $gallery->getMedia($gallery->collection_name)->pluck('id')->map(fn ($id): string => (string) $id)->all();

        if ($ids === []) {
            Flux::toast(text: __('Galerija nema fotografija za regeneriranje.'), variant: 'warning');

            return;
        }

        Artisan::call('media-library:regenerate', [
            'modelType' => Gallery::class,
            '--ids' => $ids,
            '--with-responsive-images' => (bool) config('gallery.conversions.generate_responsive_images', false),
        ]);

        $gallery->touch();

        Flux::toast(
            heading: __('Regeneriranje završeno'),
            text: $gallery->displayTitle(),
            variant: 'success',
        );
    }

    public function regenerateAll(): void
    {
        $ids = Gallery::query()
            ->forCurrentTenant()
            ->with('media')
            ->get()
            ->flatMap(fn (Gallery $gallery) => $gallery->getMedia($gallery->collection_name)->pluck('id'))
            ->map(fn ($id): string => (string) $id)
            ->values()
            ->all();

        if ($ids === []) {
            Flux::toast(text: __('Nema fotografija za regeneriranje.'), variant: 'warning');

            return;
        }

        Artisan::call('media-library:regenerate', [
            'modelType' => Gallery::class,
            '--ids' => $ids,
            '--with-responsive-images' => (bool) config('gallery.conversions.generate_responsive_images', false),
        ]);

        Flux::toast(
            heading: __('Regeneriranje završeno'),
            text: __('Sve galerije su obrađene.'),
            variant: 'success',
        );
    }

    #[Computed]
    public function galleries()
    {
        return Gallery::query()
            ->forCurrentTenant()
            ->with(['media', 'featuredMedia', 'galleryable'])
            ->when($this->search !== '', function ($query): void {
                $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $this->search).'%';

                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('title', 'like', $search)
                        ->orWhere('collection_name', 'like', $search);
                });
            })
            ->latest('created_at')
            ->latest('id')
            ->simplePaginate(18);
    }

    #[Computed]
    public function activeGallery(): ?Gallery
    {
        if ($this->activeGalleryUuid === null || $this->activeGalleryUuid === '') {
            return null;
        }

        return Gallery::query()
            ->forCurrentTenant()
            ->where('uuid', $this->activeGalleryUuid)
            ->first();
    }

    #[Computed]
    public function stats(): array
    {
        $galleries = Gallery::query()->forCurrentTenant()->with('media')->get();

        return [
            'galleries' => $galleries->count(),
            'images' => $galleries->sum(fn (Gallery $gallery): int => $gallery->getMedia($gallery->collection_name)->count()),
            'with_featured' => $galleries->whereNotNull('featured_media_id')->count(),
        ];
    }

    public function render()
    {
        return view('gallery::livewire.index')->title(__('Galerija'));
    }

    private function loadSizeSettings(): void
    {
        $this->sizeSettings = GallerySettings::imageSizes();
    }
}
