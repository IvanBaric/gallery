<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Http\Livewire;

use Flux\Flux;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Gallery\Actions\DeleteGalleryAction;
use IvanBaric\Gallery\Actions\DeleteGalleryMediaAction;
use IvanBaric\Gallery\Actions\ReorderGalleryMediaAction;
use IvanBaric\Gallery\Actions\SetFeaturedGalleryMediaAction;
use IvanBaric\Gallery\Actions\UpdateGalleryMediaMetaAction;
use IvanBaric\Gallery\Actions\UploadGalleryMediaAction;
use IvanBaric\Gallery\Concerns\HasGalleries;
use IvanBaric\Gallery\Jobs\RegenerateGalleryConversions;
use IvanBaric\Gallery\Livewire\Forms\GalleryMediaForm;
use IvanBaric\Gallery\Models\Gallery;
use IvanBaric\Gallery\Support\GalleryPermissions;
use IvanBaric\Gallery\Support\GallerySettings;
use IvanBaric\Gallery\Support\GalleryUploadValidation;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

class GalleryManager extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $modelClass;

    #[Locked]
    public int|string $modelKey;

    #[Locked]
    public string $collection = 'images';

    #[Locked]
    public string $context = 'default';

    #[Locked]
    public ?string $title = null;

    #[Locked]
    public ?string $description = null;

    #[Locked]
    public bool $migrateLegacy = true;

    /** @var array<int, TemporaryUploadedFile> */
    public array $uploads = [];

    public ?TemporaryUploadedFile $queuedUpload = null;

    public bool $selectMode = false;

    /** @var array<int, string> */
    public array $selectedMediaUuids = [];

    #[Locked]
    public ?string $pendingDeleteMediaUuid = null;

    #[Locked]
    public ?string $editingMediaUuid = null;

    public GalleryMediaForm $mediaForm;

    #[Locked]
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
        $count = count($this->uploads);
        $result = app(UploadGalleryMediaAction::class)->handle($gallery, $this->uploads, $this->collection);

        if (! $this->handleActionFailure($result, 'uploads')) {
            return;
        }

        $this->reset('uploads');
        unset($this->subject, $this->gallery, $this->mediaItems);

        Flux::toast(
            heading: __('Fotografije spremljene'),
            text: trans_choice('{1} Dodana je :count fotografija.|[2,*] Dodano je :count fotografija.', $count, ['count' => $count]),
            variant: 'success',
        );
    }

    public function saveQueuedUpload(): void
    {
        $this->authorizeGalleryAction('upload');

        if (! $this->queuedUpload instanceof TemporaryUploadedFile) {
            return;
        }

        $this->validateQueuedUpload();

        $gallery = $this->currentGallery(create: true);
        $result = app(UploadGalleryMediaAction::class)->handle($gallery, [$this->queuedUpload], $this->collection);

        if (! $this->handleActionFailure($result, 'queuedUpload')) {
            return;
        }

        $this->reset('queuedUpload');
        unset($this->subject, $this->gallery, $this->mediaItems);
    }

    public function finishQueuedUploads(int $uploadedCount, int $skippedCount = 0): void
    {
        if ($uploadedCount <= 0 && $skippedCount <= 0) {
            return;
        }

        if ($uploadedCount > 0) {
            $text = trans_choice('{1} Dodana je :count fotografija.|[2,*] Dodano je :count fotografija.', $uploadedCount, ['count' => $uploadedCount]);

            if ($skippedCount > 0) {
                $text .= ' '.trans_choice('{1} Jedna fotografija nije dodana jer je dosegnut limit.|[2,*] :count fotografija nije dodano jer je dosegnut limit.', $skippedCount, ['count' => $skippedCount]);
            }

            Flux::toast(
                heading: __('Fotografije spremljene'),
                text: $text,
                variant: $skippedCount > 0 ? 'warning' : 'success',
            );

            return;
        }

        Flux::toast(
            heading: __('Kapacitet popunjen'),
            text: trans_choice('{1} Nije dodana odabrana fotografija jer je dosegnut limit.|[2,*] Nije dodano :count odabranih fotografija jer je dosegnut limit.', $skippedCount, ['count' => $skippedCount]),
            variant: 'warning',
        );
    }

    public function toggleSelectionMode(): void
    {
        if ($this->mediaItems->isEmpty()) {
            return;
        }

        $this->selectMode = ! $this->selectMode;

        if (! $this->selectMode) {
            $this->selectedMediaUuids = [];
        }
    }

    public function selectAllMedia(): void
    {
        $this->selectMode = true;
        $this->selectedMediaUuids = $this->mediaItems
            ->pluck('uuid')
            ->map(fn ($uuid): string => (string) $uuid)
            ->all();
    }

    public function clearSelectedMedia(): void
    {
        $this->selectedMediaUuids = [];
    }

    public function updatedSelectedMediaUuids(): void
    {
        $this->selectedMediaUuids = collect($this->selectedMediaUuids)
            ->map(fn ($uuid): string => trim((string) $uuid))
            ->intersect($this->mediaItems->pluck('uuid')->map(fn ($uuid): string => (string) $uuid))
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
        $count = $media->count();
        $result = app(DeleteGalleryMediaAction::class)->handle($gallery, $media->all());

        if (! $this->handleActionFailure($result, 'selectedMediaUuids')) {
            return;
        }

        $this->deleteAttachedGalleryIfEmpty($gallery);

        $this->selectedMediaUuids = [];
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

    public function reorderMedia(string $mediaUuid, int $position): void
    {
        $gallery = $this->currentGallery()->fresh();
        $media = $this->mediaInGallery($mediaUuid);

        $result = app(ReorderGalleryMediaAction::class)->handle($gallery, (int) $media->id, $position, $this->collection);

        if (! $this->handleActionFailure($result, 'media')) {
            return;
        }

        $gallery->unsetRelation('media');
        unset($this->subject, $this->gallery, $this->mediaItems);
    }

    public function setFeaturedMedia(string $mediaUuid): void
    {
        $gallery = $this->currentGallery();
        $media = $this->mediaInGallery($mediaUuid);
        $result = app(SetFeaturedGalleryMediaAction::class)->handle($gallery, (int) $media->id);

        if (! $this->handleActionFailure($result, 'media')) {
            return;
        }

        unset($this->gallery, $this->mediaItems);

        Flux::toast(
            heading: __('Istaknuta fotografija postavljena'),
            text: __('Promjena je odmah spremljena.'),
            variant: 'success',
        );
    }

    public function editMedia(string $mediaUuid): void
    {
        $this->authorizeGalleryAction('seo');

        $media = $this->mediaInGallery($mediaUuid);

        $this->editingMediaUuid = (string) $media->uuid;
        $this->mediaForm->fillFromMedia($media);
        $gallery = $this->currentGallery();
        $this->mediaForm->lock_version = method_exists($gallery, 'getLockVersion') ? $gallery->getLockVersion() : (int) ($gallery->lock_version ?? 0);

        $this->dispatch('modal-show', name: $this->metaModalName());
    }

    public function saveMediaMeta(): void
    {
        $this->authorizeGalleryAction('seo');

        if (! $this->editingMediaUuid) {
            return;
        }

        $this->mediaForm->validate();

        $media = $this->mediaInGallery($this->editingMediaUuid);
        $result = app(UpdateGalleryMediaMetaAction::class)->handle(
            $this->currentGallery(),
            $media,
            $this->mediaForm->data(),
        );

        if (! $this->handleActionFailure($result, 'mediaForm')) {
            return;
        }

        $this->dispatch('modal-close', name: $this->metaModalName());
        $this->reset('editingMediaUuid');
        unset($this->gallery, $this->mediaItems);

        Flux::toast(
            heading: __('SEO podaci spremljeni'),
            text: __('Podaci fotografije su ažurirani.'),
            variant: 'success',
        );
    }

    public function confirmDeleteMedia(string $mediaUuid): void
    {
        $this->authorizeGalleryAction('delete');

        $media = $this->mediaInGallery($mediaUuid);
        $this->pendingDeleteMediaUuid = (string) $media->uuid;
        $this->dispatch('modal-show', name: $this->deleteModalName());
    }

    public function cancelDeleteMedia(): void
    {
        $this->pendingDeleteMediaUuid = null;
        $this->dispatch('modal-close', name: $this->deleteModalName());
    }

    public function deleteMedia(): void
    {
        $this->authorizeGalleryAction('delete');

        if (! $this->pendingDeleteMediaUuid) {
            return;
        }

        $media = $this->mediaInGallery($this->pendingDeleteMediaUuid);
        $gallery = $this->currentGallery();
        $result = app(DeleteGalleryMediaAction::class)->handle($gallery, [$media]);

        if (! $this->handleActionFailure($result, 'pendingDeleteMediaUuid')) {
            return;
        }

        $this->deleteAttachedGalleryIfEmpty($gallery);

        $this->pendingDeleteMediaUuid = null;
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
        if (! $this->pendingDeleteMediaUuid) {
            return null;
        }

        return $this->mediaItems->firstWhere('uuid', $this->pendingDeleteMediaUuid);
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

    public function friendlyUploadError(string $message): string
    {
        return GalleryUploadValidation::friendlyMessage(
            $message,
            GallerySettings::validationForContext($this->context),
        );
    }

    private function validateUploadPayload(): void
    {
        if ($this->remainingSlots <= 0) {
            throw ValidationException::withMessages([
                'uploads' => __('Dosegnut je maksimalan broj fotografija za ovu galeriju.'),
            ]);
        }

        $validation = GallerySettings::validationForContext($this->context);

        $this->validate(
            GalleryUploadValidation::rules($validation, $this->remainingSlots),
            GalleryUploadValidation::messages($validation, $this->remainingSlots),
            GalleryUploadValidation::attributes(),
        );
    }

    private function validateQueuedUpload(): void
    {
        if ($this->remainingSlots <= 0) {
            throw ValidationException::withMessages([
                'queuedUpload' => __('Dosegnut je maksimalan broj fotografija za ovu galeriju.'),
            ]);
        }

        $validation = GallerySettings::validationForContext($this->context);

        $this->validate(
            GalleryUploadValidation::singleRules($validation),
            GalleryUploadValidation::messages($validation, $this->remainingSlots),
            GalleryUploadValidation::attributes(),
        );
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

    private function handleActionFailure(ActionResult $result, string $errorBag): bool
    {
        if ($result->success) {
            return true;
        }

        foreach ($result->errors as $field => $messages) {
            foreach ((array) $messages as $message) {
                $this->addError($errorBag === $field ? $field : $errorBag.'.'.$field, (string) $message);
            }
        }

        Flux::toast(
            heading: __('Radnja nije uspjela'),
            text: $result->message,
            variant: 'danger',
        );

        return false;
    }

    private function mediaInGallery(string $mediaUuid): SpatieMedia
    {
        $media = $this->mediaItems->firstWhere('uuid', $mediaUuid);

        abort_unless($media, 404);

        return $media;
    }

    private function selectedMedia()
    {
        $uuids = collect($this->selectedMediaUuids)
            ->map(fn ($uuid): string => trim((string) $uuid))
            ->filter()
            ->all();

        if ($uuids === []) {
            return collect();
        }

        return $this->mediaItems
            ->filter(fn (SpatieMedia $media): bool => in_array((string) $media->uuid, $uuids, true))
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

        $this->handleActionFailure(app(DeleteGalleryAction::class)->handle($gallery), 'gallery');
    }
}
