<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Http\Livewire;

use Flux\Flux;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use IvanBaric\Gallery\Concerns\HasGalleries;
use IvanBaric\Gallery\Models\Gallery;
use IvanBaric\Gallery\Support\GalleryPermissions;
use Livewire\Attributes\Computed;
use Livewire\Component;

class StandaloneGallerySelector extends Component
{
    public string $modelClass;

    public int|string $modelKey;

    public string $collection = 'images';

    public bool $emptyOnly = true;

    public bool $allowReplace = false;

    public bool $showCurrent = true;

    public ?string $label = null;

    public ?string $placeholder = null;

    public ?string $buttonLabel = null;

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

        $this->validate([
            'selectedGalleryUuid' => ['required', 'string'],
        ], [
            'selectedGalleryUuid.required' => __('Odaberite samostalnu galeriju.'),
        ], [
            'selectedGalleryUuid' => __('samostalna galerija'),
        ]);

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
            throw ValidationException::withMessages([
                'selectedGalleryUuid' => $exception->getMessage(),
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

    #[Computed]
    public function subject(): Model
    {
        $modelClass = $this->modelClass;

        return $modelClass::query()->findOrFail($this->modelKey);
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
}
