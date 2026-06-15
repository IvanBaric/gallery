<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Http\Livewire;

use Flux\Flux;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use IvanBaric\Gallery\Concerns\HasGalleries;
use IvanBaric\Gallery\Contracts\TenantResolver;
use IvanBaric\Gallery\Models\Gallery;
use IvanBaric\Gallery\Support\GalleryPermissions;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class StandaloneGallerySelector extends Component
{
    #[Locked]
    public string $modelClass;

    #[Locked]
    public int|string $modelKey;

    #[Locked]
    public string $collection = 'images';

    #[Locked]
    public bool $emptyOnly = true;

    #[Locked]
    public bool $allowReplace = false;

    #[Locked]
    public bool $showCurrent = true;

    #[Locked]
    public ?string $label = null;

    #[Locked]
    public ?string $placeholder = null;

    #[Locked]
    public ?string $buttonLabel = null;

    #[Locked]
    public ?string $description = null;

    public ?string $selectedGalleryUuid = null;

    public function mount(
        Model $model,
        string $collection = 'images',
        bool $emptyOnly = true,
        bool $allowReplace = false,
        bool $showCurrent = true,
        ?string $label = null,
        ?string $placeholder = null,
        ?string $buttonLabel = null,
        ?string $description = null,
    ): void {
        $this->authorizeGalleryAction('view');

        $this->modelClass = $model::class;
        $this->modelKey = $model->getKey();
        $this->collection = $collection;
        $this->emptyOnly = $emptyOnly;
        $this->allowReplace = $allowReplace;
        $this->showCurrent = $showCurrent;
        $this->label = $label;
        $this->placeholder = $placeholder;
        $this->buttonLabel = $buttonLabel;
        $this->description = $description;
    }

    public function attachSelectedGallery(): void
    {
        $this->authorizeGalleryAction('attach');

        try {
            $this->validate([
                'selectedGalleryUuid' => ['required', 'string'],
            ], [
                'selectedGalleryUuid.required' => __('Odaberite samostalnu galeriju.'),
            ], [
                'selectedGalleryUuid' => __('samostalna galerija'),
            ]);
        } catch (ValidationException $exception) {
            Flux::toast(variant: 'danger', text: __('Odaberite galeriju i pokušajte ponovno.'));

            throw $exception;
        }

        if ($this->currentGallery && ! $this->allowReplace) {
            throw ValidationException::withMessages([
                'selectedGalleryUuid' => __('Model već ima galeriju za ovu kolekciju.'),
            ]);
        }

        $gallery = Gallery::query()
            ->forCurrentTenant()
            ->standalone()
            ->forCollection($this->collection)
            ->when($this->emptyOnly, fn ($query) => $query->empty())
            ->where('uuid', $this->selectedGalleryUuid)
            ->first();

        if (! $gallery) {
            Flux::toast(variant: 'danger', text: __('Odabrana galerija nije dostupna za povezivanje.'));

            throw ValidationException::withMessages([
                'selectedGalleryUuid' => __('Odabrana galerija nije dostupna za dodjelu.'),
            ]);
        }

        if (! method_exists($this->subject, 'attachStandaloneGallery')) {
            throw new \RuntimeException('The model must use '.HasGalleries::class.'.');
        }

        try {
            $attached = $this->subject->attachStandaloneGallery(
                $gallery,
                $this->collection,
                $this->allowReplace,
                $this->emptyOnly,
            );
        } catch (InvalidArgumentException $exception) {
            $message = $this->attachmentErrorMessage($exception->getMessage());

            Flux::toast(variant: 'danger', text: $message);

            throw ValidationException::withMessages([
                'selectedGalleryUuid' => $message,
            ]);
        }

        $this->selectedGalleryUuid = null;
        unset($this->subject, $this->currentGallery, $this->standaloneGalleries);

        $this->dispatch('gallery-attached', id: $attached->getKey(), uuid: $attached->uuid, collection: $this->collection);

        Flux::toast(
            heading: __('Galerija dodijeljena'),
            text: __('Samostalna galerija je povezana s ovim zapisom.'),
            variant: 'success',
        );
    }

    public function detachCurrentGallery(): void
    {
        $this->authorizeGalleryAction('attach');

        $gallery = $this->currentGallery;

        if (! $gallery) {
            Flux::toast(variant: 'warning', text: __('Ovaj zapis nema povezanu galeriju.'));

            return;
        }

        $gallery->forceFill([
            'galleryable_type' => null,
            'galleryable_id' => null,
        ])->save();

        $this->selectedGalleryUuid = null;
        unset($this->subject, $this->currentGallery, $this->standaloneGalleries);

        $this->dispatch('gallery-detached', id: $gallery->getKey(), uuid: $gallery->uuid, collection: $this->collection);

        Flux::modal('gallery-detach-confirm')->close();
        Flux::toast(
            heading: __('Galerija uklonjena'),
            text: __('Galerija više nije povezana s ovim zapisom.'),
            variant: 'success',
        );
    }

    #[Computed]
    public function subject(): Model
    {
        $modelClass = $this->modelClass;

        $model = $modelClass::query()->findOrFail($this->modelKey);
        $tenantId = app(TenantResolver::class)->id();

        if ($tenantId !== null && $model->getAttribute('team_id') !== null) {
            abort_unless((string) $model->getAttribute('team_id') === (string) $tenantId, 404);
        }

        return $model;
    }

    #[Computed]
    public function currentGallery(): ?Gallery
    {
        if (! method_exists($this->subject, 'gallery')) {
            throw new \RuntimeException('The model must use '.HasGalleries::class.'.');
        }

        return $this->subject->gallery($this->collection);
    }

    /**
     * @return Collection<int, Gallery>
     */
    #[Computed]
    public function standaloneGalleries(): Collection
    {
        return Gallery::query()
            ->forCurrentTenant()
            ->standalone()
            ->forCollection($this->collection)
            ->when($this->emptyOnly, fn ($query) => $query->empty())
            ->orderBy('title')
            ->latest('id')
            ->limit(100)
            ->get();
    }

    public function render()
    {
        return view('gallery::livewire.standalone-selector');
    }

    public function allowsGalleryAction(string $action): bool
    {
        return GalleryPermissions::allows(auth()->user(), $action);
    }

    private function authorizeGalleryAction(string $action): void
    {
        GalleryPermissions::authorize($action);
    }

    private function attachmentErrorMessage(string $message): string
    {
        return match ($message) {
            'The model must be saved before attaching a gallery.' => __('Zapis mora biti spremljen prije povezivanja galerije.'),
            'Only standalone galleries can be attached.' => __('Moguće je povezati samo samostalne galerije.'),
            'Only empty standalone galleries can be attached.' => __('Moguće je povezati samo prazne samostalne galerije.'),
            'The model already has a gallery for this collection.' => __('Zapis već ima galeriju za ovu kolekciju.'),
            default => __('Galeriju nije moguće povezati.'),
        };
    }
}
