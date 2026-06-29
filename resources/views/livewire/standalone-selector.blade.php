@php
    $currentGallery = $this->currentGallery;
    $standaloneGalleries = $this->standaloneGalleries;
    $canAttach = $this->allowsGalleryAction('attach');
    $selectLabel = $label ?: __('Samostalna galerija');
    $selectPlaceholder = $placeholder ?: ($emptyOnly ? __('Odaberite praznu samostalnu galeriju') : __('Odaberite samostalnu galeriju'));
    $submitLabel = $buttonLabel ?: __('Dodijeli galeriju');
    $hasCurrentGallery = (bool) $currentGallery;
    $canChooseGallery = $canAttach && $standaloneGalleries->isNotEmpty();
@endphp

<div class="space-y-3">
    @if ($showCurrent && $currentGallery)
        <div class="rounded-xl bg-zinc-50/70 p-4 ring-1 ring-zinc-950/5 dark:bg-zinc-900/80 dark:ring-white/10">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-medium uppercase text-zinc-400 dark:text-zinc-500">{{ __('Povezana galerija') }}</p>
                    <p class="mt-1 text-sm font-semibold text-zinc-950 dark:text-white">{{ $currentGallery->displayTitle() }}</p>
                </div>

                @if ($canAttach)
                    <flux:modal.trigger name="gallery-detach-confirm">
                        <flux:tooltip :content="__('Odspoji galeriju od ovog zapisa bez brisanja galerije')">
                            <flux:button type="button" variant="ghost" size="sm" icon="x-mark">
                                {{ __('Ukloni vezu') }}
                            </flux:button>
                        </flux:tooltip>
                    </flux:modal.trigger>
                @endif
            </div>
        </div>
    @endif

    @if ($canChooseGallery)
        <div class="space-y-3">
            <flux:select
                wire:model.live="selectedGalleryUuid"
                :label="$hasCurrentGallery ? __('Odaberi drugu galeriju') : $selectLabel"
                :placeholder="$hasCurrentGallery ? __('Odaberite novu galeriju') : $selectPlaceholder"
                variant="listbox"
                searchable
                clearable
                class="w-full"
            >
                <x-slot name="search">
                    <flux:select.search class="px-4" :placeholder="__('Pretraži galerije...')" />
                </x-slot>

                @foreach ($standaloneGalleries as $gallery)
                    <flux:select.option value="{{ $gallery->uuid }}">
                        {{ $gallery->displayTitle() }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <div class="w-full">
                <flux:tooltip :content="$hasCurrentGallery ? __('Zamijeni trenutnu galeriju odabranom galerijom') : __('Poveži odabranu galeriju s ovim zapisom')" class="block w-full">
                    <flux:button
                        type="button"
                        variant="primary"
                        icon="link"
                        wire:click="attachSelectedGallery"
                        wire:loading.attr="disabled"
                        wire:target="attachSelectedGallery"
                        :disabled="blank($selectedGalleryUuid)"
                        class="w-full justify-center"
                    >
                        {{ $hasCurrentGallery ? __('Poveži odabranu') : $submitLabel }}
                    </flux:button>
                </flux:tooltip>
            </div>
        </div>
    @endif

    @if ($description)
        <p class="text-sm leading-5 text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
    @endif

    @if ($standaloneGalleries->isEmpty())
        <p class="text-sm leading-5 text-zinc-500 dark:text-zinc-400">
            {{ $hasCurrentGallery ? __('Nema dostupnih samostalnih galerija za zamjenu.') : ($emptyOnly ? __('Nema dostupnih praznih samostalnih galerija.') : __('Nema dostupnih samostalnih galerija.')) }}
        </p>
    @endif

    @error('selectedGalleryUuid')
        <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror

    <flux:modal name="gallery-detach-confirm" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Ukloniti vezu s galerijom?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Galerija neće biti obrisana. Samo će se odspojiti od ovog zapisa i ponovno biti dostupna za povezivanje.') }}</flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Odustani') }}</flux:button>
                </flux:modal.close>
                <flux:button type="button" wire:click="detachCurrentGallery" wire:loading.attr="disabled" wire:target="detachCurrentGallery" variant="danger" icon="x-mark">
                    {{ __('Ukloni vezu') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
