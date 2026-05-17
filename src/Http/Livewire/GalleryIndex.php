<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Http\Livewire;

use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use IvanBaric\Gallery\Jobs\RegenerateGalleryConversions;
use IvanBaric\Gallery\Models\Gallery;
use IvanBaric\Gallery\Support\GalleryPermissions;
use IvanBaric\Gallery\Support\GallerySettings;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class GalleryIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $sortField = 'updated_at';

    public string $sortDirection = 'desc';

    public string $filter = 'all';

    public array $sizeSettings = [];

    public array $createForm = [
        'title' => '',
    ];

    public function mount(): void
    {
        $this->authorizeGalleryAction('view');
        $this->loadSizeSettings();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function setFilter(string $filter): void
    {
        if (! array_key_exists($filter, $this->filterOptions)) {
            return;
        }

        $this->filter = $filter;
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['images_count', 'created_at', 'updated_at'], true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'desc';
        }

        $this->resetPage();
    }

    public function openSettings(): void
    {
        $this->authorizeGalleryAction('settings');
        $this->loadSizeSettings();
        $this->dispatch('modal-show', name: 'gallery-settings');
    }

    public function openRegenerateAllConfirmation(): void
    {
        $this->authorizeGalleryAction('regenerate');
        $this->dispatch('modal-show', name: 'gallery-regenerate-all-confirm');
    }

    public function openCreateGalleryModal(): void
    {
        $this->authorizeGalleryAction('create');
        $this->createForm = ['title' => ''];
        $this->resetValidation('createForm.title');
        $this->dispatch('modal-show', name: 'gallery-create');
    }

    public function createGallery(): void
    {
        $this->authorizeGalleryAction('create');

        $validated = $this->validate([
            'createForm.title' => ['required', 'string', 'max:180'],
        ], [
            'createForm.title.required' => __('Unesite naziv galerije.'),
            'createForm.title.max' => __('Naziv galerije može imati najviše :max znakova.'),
        ], [
            'createForm.title' => __('naziv galerije'),
        ]);

        $gallery = Gallery::query()->create([
            'title' => trim((string) $validated['createForm']['title']),
            'collection_name' => 'images',
        ]);

        $this->dispatch('modal-close', name: 'gallery-create');

        Flux::toast(
            heading: __('Galerija kreirana'),
            text: __('Nova galerija ":title" je spremna za dodavanje slika.', ['title' => $gallery->displayTitle()]),
            variant: 'success',
        );

        $this->redirectRoute('admin.galleries.edit', ['uuid' => $gallery->uuid], navigate: true);
    }

    public function openGallery(string $uuid): void
    {
        $this->authorizeGalleryAction('view');

        $exists = Gallery::query()
            ->forCurrentTenant()
            ->where('uuid', $uuid)
            ->exists();

        abort_unless($exists, 404);

        $this->redirectRoute('admin.galleries.edit', ['uuid' => $uuid], navigate: true);
    }

    public function saveSettings(): void
    {
        $this->authorizeGalleryAction('settings');

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
        $this->authorizeGalleryAction('regenerate');

        $gallery = Gallery::query()->forCurrentTenant()->where('uuid', $uuid)->firstOrFail();
        $ids = $gallery->getMedia($gallery->collection_name)->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($ids === []) {
            Flux::toast(text: __('Galerija nema fotografija za regeneriranje.'), variant: 'warning');

            return;
        }

        $gallery->markRegenerationQueued(count($ids));
        RegenerateGalleryConversions::dispatch((int) $gallery->getKey(), $ids);

        Flux::toast(
            heading: __('Regeneriranje pokrenuto'),
            text: __('Galerija ":title" je poslana u obradu.', ['title' => $gallery->displayTitle()]),
            variant: 'success',
        );
    }

    public function regenerateAll(): void
    {
        $this->authorizeGalleryAction('regenerate');

        $galleries = Gallery::query()
            ->forCurrentTenant()
            ->with('media')
            ->get()
            ->filter(fn (Gallery $gallery): bool => $gallery->getMedia($gallery->collection_name)->isNotEmpty());

        if ($galleries->isEmpty()) {
            Flux::toast(text: __('Nema fotografija za regeneriranje.'), variant: 'warning');

            return;
        }

        foreach ($galleries as $gallery) {
            $ids = $gallery->getMedia($gallery->collection_name)->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $gallery->markRegenerationQueued(count($ids));
            RegenerateGalleryConversions::dispatch((int) $gallery->getKey(), $ids);
        }

        $this->dispatch('modal-close', name: 'gallery-regenerate-all-confirm');

        Flux::toast(
            heading: __('Regeneriranje pokrenuto'),
            text: trans_choice('{1} Jedna galerija je poslana u obradu.|[2,*] :count galerija je poslano u obradu.', $galleries->count(), ['count' => $galleries->count()]),
            variant: 'success',
        );
    }

    #[Computed]
    public function filterOptions(): array
    {
        $stats = $this->stats;

        return [
            'all' => ['label' => __('Sve'), 'count' => $stats['galleries']],
            'empty' => ['label' => __('Prazne'), 'count' => $stats['empty']],
            'without_featured' => ['label' => __('Bez istaknute slike'), 'count' => $stats['without_featured']],
        ];
    }

    #[Computed]
    public function galleries()
    {
        return Gallery::query()
            ->forCurrentTenant()
            ->with(['media', 'featuredMedia', 'galleryable'])
            ->withCount('media as images_count')
            ->when($this->search !== '', function ($query): void {
                $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $this->search).'%';

                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('title', 'like', $search)
                        ->orWhere('collection_name', 'like', $search);
                });
            })
            ->when($this->filter === 'empty', fn (Builder $query): Builder => $query->whereDoesntHave('media'))
            ->when($this->filter === 'without_featured', fn (Builder $query): Builder => $query->whereNull('featured_media_id')->has('media'))
            ->orderBy($this->sortField, $this->sortDirection)
            ->latest('id')
            ->simplePaginate(18);
    }

    #[Computed]
    public function stats(): array
    {
        $galleries = Gallery::query()->forCurrentTenant()->with('media')->get();

        return [
            'galleries' => $galleries->count(),
            'images' => $galleries->sum(fn (Gallery $gallery): int => $gallery->getMedia($gallery->collection_name)->count()),
            'with_featured' => $galleries->whereNotNull('featured_media_id')->count(),
            'standalone' => $galleries->whereNull('galleryable_type')->count(),
            'vehicles' => $galleries->filter(fn (Gallery $gallery): bool => class_basename((string) $gallery->galleryable_type) === 'Car')->count(),
            'empty' => $galleries->filter(fn (Gallery $gallery): bool => $gallery->getMedia($gallery->collection_name)->isEmpty())->count(),
            'without_featured' => $galleries->filter(fn (Gallery $gallery): bool => blank($gallery->featured_media_id) && $gallery->getMedia($gallery->collection_name)->isNotEmpty())->count(),
        ];
    }

    #[Computed]
    public function regenerationSummary(): array
    {
        $galleries = Gallery::query()
            ->forCurrentTenant()
            ->with('media')
            ->get()
            ->filter(fn (Gallery $gallery): bool => $gallery->getMedia($gallery->collection_name)->isNotEmpty());

        $lastRegeneratedAt = $galleries
            ->map(fn (Gallery $gallery) => $gallery->lastRegeneratedAt())
            ->filter()
            ->sortByDesc(fn ($date): int => $date->getTimestamp())
            ->first();

        return [
            'galleries' => $galleries->count(),
            'images' => $galleries->sum(fn (Gallery $gallery): int => $gallery->getMedia($gallery->collection_name)->count()),
            'queued' => $galleries->filter(fn (Gallery $gallery): bool => $gallery->regenerationQueuedAt() !== null)->count(),
            'last_regenerated_at' => $lastRegeneratedAt,
        ];
    }

    public function render()
    {
        return view('gallery::livewire.index')->title(__('Galerija'));
    }

    public function allowsGalleryAction(string $action): bool
    {
        return GalleryPermissions::allows(auth()->user(), $action);
    }

    private function loadSizeSettings(): void
    {
        $this->sizeSettings = GallerySettings::imageSizes();
    }

    private function authorizeGalleryAction(string $action): void
    {
        GalleryPermissions::authorize($action);
    }

}
