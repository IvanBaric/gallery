<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Http\Livewire;

use Flux\Flux;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use IvanBaric\Gallery\Concerns\HasGalleries;
use IvanBaric\Gallery\Jobs\RegenerateGalleryConversions;
use IvanBaric\Gallery\Models\Gallery;
use IvanBaric\Gallery\Models\Media;
use IvanBaric\Gallery\Support\GalleryPermissions;
use IvanBaric\Gallery\Support\GallerySettings;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

class GalleryManager extends Component
{
    use WithFileUploads;

    public string $modelClass;

    public int|string $modelKey;

    public string $collection = 'images';

    public string $context = 'default';

    public ?string $title = null;

    public ?string $description = null;

    public bool $migrateLegacy = true;

    /** @var array<int, TemporaryUploadedFile> */
    public array $uploads = [];

    public bool $selectMode = false;

    /** @var array<int, int|string> */
    public array $selectedMediaIds = [];

    public ?int $pendingDeleteMediaId = null;

    public ?int $editingMediaId = null;

    public array $mediaForm = [
        'alt' => '',
        'title' => '',
        'caption' => '',
        'description' => '',
        'credit' => '',
        'source_url' => '',
        'license' => '',
        'is_decorative' => false,
    ];

    public string $modalKey;

    public function mount(
        Model $model,
        string $collection = 'images',
        string $context = 'default',
        ?string $title = null,
        ?string $description = null,
        bool $migrateLegacy = true,
    ): void {
        $this->authorizeGalleryAction('view');

        $this->modelClass = $model::class;
        $this->modelKey = $model->getKey();
        $this->collection = $collection;
        $this->context = $context;
        $this->title = $title;
        $this->description = $description;
        $this->migrateLegacy = $migrateLegacy;
        $this->modalKey = md5($this->modelClass.'|'.$this->modelKey.'|'.$this->collection);

        if ($this->migrateLegacy && method_exists($model, 'migrateMediaCollectionToGallery')) {
            $model->migrateMediaCollectionToGallery($this->collection);
        }
    }

    public function updatedUploads(): void
    {
        $this->saveUploads();
    }

    public function saveUploads(): void
    {
        $this->authorizeGalleryAction('upload');

        if ($this->uploads === []) {
            return;
        }

        $this->validateUploadPayload();

        $gallery = $this->currentGallery(create: true);

        foreach ($this->uploads as $upload) {
            $gallery
                ->addMedia($upload->getRealPath())
                ->usingFileName($upload->hashName())
                ->usingName(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME) ?: $upload->hashName())
                ->withCustomProperties([
                    'alt' => '',
                    'title' => '',
                    'caption' => '',
                    'description' => '',
                    'credit' => '',
                    'source_url' => '',
                    'license' => '',
                    'is_decorative' => false,
                ])
                ->toMediaCollection($this->collection);
        }

        $count = count($this->uploads);
        $this->reset('uploads');
        unset($this->subject, $this->gallery, $this->mediaItems);

        Flux::toast(
            heading: __('Fotografije spremljene'),
            text: trans_choice('{1} Dodana je :count fotografija.|[2,*] Dodano je :count fotografija.', $count, ['count' => $count]),
            variant: 'success',
        );
    }

    public function toggleSelectionMode(): void
    {
        if ($this->mediaItems->isEmpty()) {
            return;
        }

        $this->selectMode = ! $this->selectMode;

        if (! $this->selectMode) {
            $this->selectedMediaIds = [];
        }
    }

    public function selectAllMedia(): void
    {
        $this->selectMode = true;
        $this->selectedMediaIds = $this->mediaItems
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function clearSelectedMedia(): void
    {
        $this->selectedMediaIds = [];
    }

    public function updatedSelectedMediaIds(): void
    {
        $this->selectedMediaIds = collect($this->selectedMediaIds)
            ->map(fn ($id): int => (int) $id)
            ->intersect($this->mediaItems->pluck('id')->map(fn ($id): int => (int) $id))
            ->values()
            ->all();
    }

    public function confirmDeleteSelectedMedia(): void
    {
        $this->authorizeGalleryAction('delete');

        if ($this->selectedMediaCount <= 0) {
            Flux::toast(text: __('Odaberite barem jednu fotografiju.'), variant: 'warning');

            return;
        }

        $this->dispatch('modal-show', name: $this->bulkDeleteModalName());
    }

    public function deleteSelectedMedia(): void
    {
        $this->authorizeGalleryAction('delete');

        $media = $this->selectedMedia();

        if ($media->isEmpty()) {
            $this->dispatch('modal-close', name: $this->bulkDeleteModalName());
            $this->selectedMediaIds = [];

            return;
        }

        $gallery = $this->currentGallery();
        $featuredDeleted = $media->contains(fn (SpatieMedia $item): bool => (int) $gallery->featured_media_id === (int) $item->id);

        foreach ($media as $item) {
            $item->delete();
        }

        if ($featuredDeleted) {
            $gallery->forceFill(['featured_media_id' => null])->save();
        } else {
            $gallery->touch();
        }

        $this->deleteAttachedGalleryIfEmpty($gallery);

        $count = $media->count();
        $this->selectedMediaIds = [];
        $this->selectMode = false;
        $this->dispatch('modal-close', name: $this->bulkDeleteModalName());
        unset($this->gallery, $this->mediaItems);

        Flux::toast(
            heading: __('Fotografije obrisane'),
            text: trans_choice('{1} Obrisana je :count fotografija.|[2,*] Obrisano je :count fotografija.', $count, ['count' => $count]),
            variant: 'success',
        );
    }

    public function regenerateSelectedMedia(): void
    {
        $this->authorizeGalleryAction('regenerate');

        $ids = $this->selectedMedia()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($ids === []) {
            Flux::toast(text: __('Odaberite barem jednu fotografiju.'), variant: 'warning');

            return;
        }

        $gallery = $this->currentGallery();
        $gallery->markRegenerationQueued(count($ids));
        RegenerateGalleryConversions::dispatch((int) $gallery->getKey(), $ids);
        unset($this->gallery, $this->mediaItems);

        Flux::toast(
            heading: __('Regeneriranje pokrenuto'),
            text: trans_choice('{1} Jedna odabrana fotografija je poslana u obradu.|[2,*] :count odabranih fotografija je poslano u obradu.', count($ids), ['count' => count($ids)]),
            variant: 'success',
        );
    }

    public function reorderMedia(int $mediaId, int $position): void
    {
        $this->authorizeGalleryAction('update');

        $gallery = $this->currentGallery()->fresh();

        $ids = $gallery->getMedia($this->collection)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->reject(fn (int $id): bool => $id === $mediaId)
            ->values()
            ->all();

        $position = max(0, min($position, count($ids)));
        array_splice($ids, $position, 0, [$mediaId]);

        Media::setNewOrder($ids);

        $gallery->touch();
        $gallery->unsetRelation('media');
        unset($this->subject, $this->gallery, $this->mediaItems);
    }

    public function setFeaturedMedia(int $mediaId): void
    {
        $this->authorizeGalleryAction('update');

        $media = $this->mediaInGallery($mediaId);
        $gallery = $this->currentGallery();

        $gallery->forceFill(['featured_media_id' => $media->id])->save();
        unset($this->gallery, $this->mediaItems);

        Flux::toast(
            heading: __('Istaknuta fotografija postavljena'),
            text: __('Promjena je odmah spremljena.'),
            variant: 'success',
        );
    }

    public function editMedia(int $mediaId): void
    {
        $this->authorizeGalleryAction('seo');

        $media = $this->mediaInGallery($mediaId);

        $this->editingMediaId = $media->id;
        $this->mediaForm = [
            'alt' => (string) $media->getCustomProperty('alt', ''),
            'title' => (string) $media->getCustomProperty('title', $media->name),
            'caption' => (string) $media->getCustomProperty('caption', ''),
            'description' => (string) $media->getCustomProperty('description', ''),
            'credit' => (string) $media->getCustomProperty('credit', ''),
            'source_url' => (string) $media->getCustomProperty('source_url', ''),
            'license' => (string) $media->getCustomProperty('license', ''),
            'is_decorative' => (bool) $media->getCustomProperty('is_decorative', false),
        ];

        $this->dispatch('modal-show', name: $this->metaModalName());
    }

    public function saveMediaMeta(): void
    {
        $this->authorizeGalleryAction('seo');

        if (! $this->editingMediaId) {
            return;
        }

        $validated = $this->validate([
            'mediaForm.alt' => ['nullable', 'string', 'max:180'],
            'mediaForm.title' => ['nullable', 'string', 'max:180'],
            'mediaForm.caption' => ['nullable', 'string', 'max:500'],
            'mediaForm.description' => ['nullable', 'string', 'max:2000'],
            'mediaForm.credit' => ['nullable', 'string', 'max:180'],
            'mediaForm.source_url' => ['nullable', 'url', 'max:2048'],
            'mediaForm.license' => ['nullable', 'string', 'max:180'],
            'mediaForm.is_decorative' => ['boolean'],
        ]);

        $media = $this->mediaInGallery($this->editingMediaId);
        $form = $validated['mediaForm'];

        $media->name = filled($form['title'] ?? null) ? (string) $form['title'] : $media->name;
        $media->custom_properties = array_merge($media->custom_properties ?? [], [
            'alt' => ($form['alt'] ?? '') ?: null,
            'title' => ($form['title'] ?? '') ?: null,
            'caption' => ($form['caption'] ?? '') ?: null,
            'description' => ($form['description'] ?? '') ?: null,
            'credit' => ($form['credit'] ?? '') ?: null,
            'source_url' => ($form['source_url'] ?? '') ?: null,
            'license' => ($form['license'] ?? '') ?: null,
            'is_decorative' => (bool) ($form['is_decorative'] ?? false),
        ]);
        $media->save();
        $this->currentGallery()->touch();

        $this->dispatch('modal-close', name: $this->metaModalName());
        $this->reset('editingMediaId');
        unset($this->gallery, $this->mediaItems);

        Flux::toast(
            heading: __('SEO podaci spremljeni'),
            text: __('Podaci fotografije su ažurirani.'),
            variant: 'success',
        );
    }

    public function confirmDeleteMedia(int $mediaId): void
    {
        $this->authorizeGalleryAction('delete');

        $media = $this->mediaInGallery($mediaId);
        $this->pendingDeleteMediaId = $media->id;
        $this->dispatch('modal-show', name: $this->deleteModalName());
    }

    public function cancelDeleteMedia(): void
    {
        $this->pendingDeleteMediaId = null;
        $this->dispatch('modal-close', name: $this->deleteModalName());
    }

    public function deleteMedia(): void
    {
        $this->authorizeGalleryAction('delete');

        if (! $this->pendingDeleteMediaId) {
            return;
        }

        $media = $this->mediaInGallery($this->pendingDeleteMediaId);
        $gallery = $this->currentGallery();
        $wasFeatured = (int) $gallery->featured_media_id === (int) $media->id;

        $media->delete();

        if ($wasFeatured) {
            $gallery->forceFill(['featured_media_id' => null])->save();
        } else {
            $gallery->touch();
        }

        $this->deleteAttachedGalleryIfEmpty($gallery);

        $this->pendingDeleteMediaId = null;
        $this->dispatch('modal-close', name: $this->deleteModalName());
        unset($this->gallery, $this->mediaItems);

        Flux::toast(
            heading: __('Fotografija obrisana'),
            text: __('Promjena je odmah spremljena.'),
            variant: 'success',
        );
    }

    public function regenerateGallery(): void
    {
        $this->authorizeGalleryAction('regenerate');

        $ids = $this->mediaItems->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($ids === []) {
            Flux::toast(text: __('Galerija nema fotografija za regeneriranje.'), variant: 'warning');

            return;
        }

        $gallery = $this->currentGallery();
        $gallery->markRegenerationQueued(count($ids));
        RegenerateGalleryConversions::dispatch((int) $gallery->getKey(), $ids);
        unset($this->gallery, $this->mediaItems);

        Flux::toast(
            heading: __('Regeneriranje pokrenuto'),
            text: __('Galerija je poslana u obradu.'),
            variant: 'success',
        );
    }

    #[Computed]
    public function subject(): Model
    {
        $modelClass = $this->modelClass;

        return $modelClass::query()->findOrFail($this->modelKey);
    }

    #[Computed]
    public function gallery(): ?Gallery
    {
        if ($this->subject instanceof Gallery) {
            return $this->subject;
        }

        if (! method_exists($this->subject, 'gallery')) {
            throw new \RuntimeException('The model must use '.HasGalleries::class.'.');
        }

        return $this->subject->gallery($this->collection);
    }

    #[Computed]
    public function mediaItems()
    {
        return $this->gallery?->getMedia($this->collection) ?? collect();
    }

    #[Computed]
    public function pendingDeleteMedia(): ?SpatieMedia
    {
        if (! $this->pendingDeleteMediaId) {
            return null;
        }

        return $this->mediaItems->firstWhere('id', $this->pendingDeleteMediaId);
    }

    #[Computed]
    public function maxFiles(): int
    {
        return GallerySettings::validationForContext($this->context)['max_files'];
    }

    #[Computed]
    public function maxFileSizeKb(): int
    {
        return GallerySettings::validationForContext($this->context)['max_file_size_kb'];
    }

    #[Computed]
    public function acceptedMimes(): string
    {
        return collect(GallerySettings::validationForContext($this->context)['mimes'])
            ->map(fn (string $mime): string => '.'.ltrim($mime, '.'))
            ->implode(',');
    }

    #[Computed]
    public function remainingSlots(): int
    {
        return max(0, $this->maxFiles - $this->mediaItems->count());
    }

    #[Computed]
    public function selectedMediaCount(): int
    {
        return $this->selectedMedia()->count();
    }

    #[Computed]
    public function seoCompleteCount(): int
    {
        return $this->gallery?->seoCompleteMediaCount() ?? 0;
    }

    public function metaModalName(): string
    {
        return 'gallery-media-meta-'.$this->modalKey;
    }

    public function deleteModalName(): string
    {
        return 'gallery-media-delete-'.$this->modalKey;
    }

    public function bulkDeleteModalName(): string
    {
        return 'gallery-media-bulk-delete-'.$this->modalKey;
    }

    public function render()
    {
        return view('gallery::livewire.manager');
    }

    public function allowsGalleryAction(string $action): bool
    {
        return GalleryPermissions::allows(auth()->user(), $action);
    }

    private function validateUploadPayload(): void
    {
        if ($this->remainingSlots <= 0) {
            throw ValidationException::withMessages([
                'uploads' => __('Dosegnut je maksimalan broj fotografija za ovu galeriju.'),
            ]);
        }

        $validation = GallerySettings::validationForContext($this->context);
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

        $this->validate([
            'uploads' => ['array', 'max:'.$this->remainingSlots],
            'uploads.*' => $imageRules,
        ], [
            'uploads.array' => __('Odaberite jednu ili više fotografija.'),
            'uploads.max' => __('Možete dodati najviše :max fotografija.', ['max' => $this->remainingSlots]),
            'uploads.*.image' => __('Svaka datoteka mora biti slika.'),
            'uploads.*.max' => __('Svaka fotografija mora biti manja od :max MB.', ['max' => max(1, (int) ceil($validation['max_file_size_kb'] / 1024))]),
            'uploads.*.mimes' => __('Dopušteni formati su: :values.', ['values' => strtoupper(implode(', ', $validation['mimes']))]),
            'uploads.*.dimensions' => __('Fotografija ne zadovoljava minimalne dimenzije.'),
        ], [
            'uploads' => __('fotografije'),
            'uploads.*' => __('fotografija'),
        ]);
    }

    private function currentGallery(bool $create = false): Gallery
    {
        if ($this->subject instanceof Gallery) {
            return $this->subject;
        }

        if (! method_exists($this->subject, 'gallery')) {
            throw new \RuntimeException('The model must use '.HasGalleries::class.'.');
        }

        if ($create) {
            if (! method_exists($this->subject, 'getOrCreateGallery')) {
                throw new \RuntimeException('The model must use '.HasGalleries::class.'.');
            }

            return $this->subject->getOrCreateGallery($this->collection);
        }

        $gallery = $this->subject->gallery($this->collection);

        abort_unless($gallery, 404);

        return $gallery;
    }

    private function authorizeGalleryAction(string $action): void
    {
        GalleryPermissions::authorize($action);
    }

    private function mediaInGallery(int $mediaId): SpatieMedia
    {
        $media = $this->mediaItems->firstWhere('id', $mediaId);

        abort_unless($media, 404);

        return $media;
    }

    private function selectedMedia()
    {
        $ids = collect($this->selectedMediaIds)
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($ids === []) {
            return collect();
        }

        return $this->mediaItems
            ->filter(fn (SpatieMedia $media): bool => in_array((int) $media->id, $ids, true))
            ->values();
    }

    private function deleteAttachedGalleryIfEmpty(Gallery $gallery): void
    {
        if ($this->subject instanceof Gallery) {
            return;
        }

        $gallery->unsetRelation('media');

        if ($gallery->getMedia($gallery->collection_name)->isNotEmpty()) {
            return;
        }

        $gallery->delete();
    }
}
