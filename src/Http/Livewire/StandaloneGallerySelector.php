<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Http\Livewire;

use Flux\Flux;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Gallery\Actions\AttachGalleryToModelAction;
use IvanBaric\Gallery\Actions\DetachGalleryFromModelAction;
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

        $result = app(AttachGalleryToModelAction::class)->handle(
            $this->subject,
            $gallery,
            $this->collection,
            $this->allowReplace,
            $this->emptyOnly,
        );

        if (! $this->handleActionFailure($result, 'selectedGalleryUuid')) {
            return;
        }

        /** @var Gallery $attached */
        $attached = $result->data;

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

        $result = app(DetachGalleryFromModelAction::class)->handle($this->subject, $gallery, $this->collection);

        if (! $this->handleActionFailure($result, 'gallery')) {
            return;
        }

        $this->selectedGalleryUuid = null;
        unset($this->subject, $this->currentGallery, $this->standaloneGalleries);

        $this->dispatch('gallery-detached', id: $gallery->getKey(), uuid: $gallery->uuid, collection: $this->collection);

        Flux::modal('gallery-detach-confirm')->close();
        Flux::toast(
            heading: __('Galerija uklonjena'),
            text: __('Galerija više nije povezana s ovim zapisom.'),
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
}
