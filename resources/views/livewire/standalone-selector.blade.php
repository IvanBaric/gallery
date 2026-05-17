@php
    $currentGallery = $this->currentGallery;
    $standaloneGalleries = $this->standaloneGalleries;
    $canAttach = $this->allowsGalleryAction('attach');
    $selectLabel = $label ?: __('Samostalna galerija');
    $selectPlaceholder = $placeholder ?: ($emptyOnly ? __('Odaberite praznu samostalnu galeriju') : __('Odaberite samostalnu galeriju'));
    $submitLabel = $buttonLabel ?: __('Dodijeli galeriju');
    $hasCurrentGallery = (bool) $currentGallery;
    $isLocked = $hasCurrentGallery && ! $allowReplace;
@endphp

<div class="space-y-3">
    @if ($showCurrent && $currentGallery)
        <div class="rounded-xl bg-zinc-50/70 px-4 py-3 text-sm text-zinc-600 ring-1 ring-zinc-950/5 dark:bg-zinc-900/80 dark:text-zinc-300 dark:ring-white/10">
            <span class="font-medium text-zinc-950 dark:text-white">{{ __('Trenutna galerija') }}:</span>
            <span>{{ $currentGallery->displayTitle() }}</span>
        </div>
    @endif

    <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
        <flux:select
            wire:model="selectedGalleryUuid"
            :label="$selectLabel"
            :placeholder="$selectPlaceholder"
            variant="listbox"
            searchable
            clearable
            :disabled="! $canAttach || $isLocked"
        >
            @foreach ($standaloneGalleries as $gallery)
                <flux:select.option value="{{ $gallery->uuid }}">
                    {{ $gallery->displayTitle() }}
                </flux:select.option>
            @endforeach
        </flux:select>

        <flux:button
            type="button"
            variant="primary"
            icon="link"
            wire:click="attachSelectedGallery"
            wire:loading.attr="disabled"
            wire:target="attachSelectedGallery"
            :disabled="blank($selectedGalleryUuid) || ! $canAttach || $isLocked"
        >
            {{ $submitLabel }}
        </flux:button>
    </div>

    @if ($description)
        <p class="text-sm leading-5 text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
    @endif

    @if ($isLocked)
        <p class="text-sm leading-5 text-zinc-500 dark:text-zinc-400">
            {{ __('Ovaj zapis već ima galeriju za odabranu kolekciju.') }}
        </p>
    @elseif ($standaloneGalleries->isEmpty())
        <p class="text-sm leading-5 text-zinc-500 dark:text-zinc-400">
            {{ $emptyOnly ? __('Nema dostupnih praznih samostalnih galerija.') : __('Nema dostupnih samostalnih galerija.') }}
        </p>
    @endif

    @error('selectedGalleryUuid')
        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
