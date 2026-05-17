<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Http\Livewire;

use Flux\Flux;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use IvanBaric\Gallery\Concerns\HasGalleries;
use IvanBaric\Gallery\Models\Gallery;
use IvanBaric\Gallery\Models\Media;
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
        if ($this->uploads === []) {
            return;
        }

        $this->validateUploadPayload();

        $gallery = $this->currentGallery();

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

    public function reorderMedia(int $mediaId, int $position): void
    {
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
        $ids = $this->mediaItems->pluck('id')->map(fn ($id): string => (string) $id)->all();

        if ($ids === []) {
            Flux::toast(text: __('Galerija nema fotografija za regeneriranje.'), variant: 'warning');

            return;
        }

        Artisan::call('media-library:regenerate', [
            'modelType' => Gallery::class,
            '--ids' => $ids,
            '--with-responsive-images' => (bool) config('gallery.conversions.generate_responsive_images', false),
        ]);

        Flux::toast(
            heading: __('Regeneriranje završeno'),
            text: __('Veličine slika su ponovno generirane.'),
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
    public function gallery(): Gallery
    {
        if ($this->subject instanceof Gallery) {
            return $this->subject;
        }

        if (! method_exists($this->subject, 'getOrCreateGallery')) {
            throw new \RuntimeException('The model must use '.HasGalleries::class.'.');
        }

        return $this->subject->getOrCreateGallery($this->collection);
    }

    #[Computed]
    public function mediaItems()
    {
        return $this->gallery->getMedia($this->collection);
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

    public function metaModalName(): string
    {
        return 'gallery-media-meta-'.$this->modalKey;
    }

    public function deleteModalName(): string
    {
        return 'gallery-media-delete-'.$this->modalKey;
    }

    public function render()
    {
        return view('gallery::livewire.manager');
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
        ]);
    }

    private function currentGallery(): Gallery
    {
        return $this->gallery;
    }

    private function mediaInGallery(int $mediaId): SpatieMedia
    {
        $media = $this->mediaItems->firstWhere('id', $mediaId);

        abort_unless($media, 404);

        return $media;
    }
}
