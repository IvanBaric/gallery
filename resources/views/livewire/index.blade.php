@php
    $stats = $this->stats;
    $cards = [
        ['label' => __('Galerije'), 'value' => number_format($stats['galleries'], 0, ',', ' '), 'icon' => 'photo', 'accent' => 'bg-zinc-900 dark:bg-white'],
        ['label' => __('Fotografije'), 'value' => number_format($stats['images'], 0, ',', ' '), 'icon' => 'squares-2x2', 'accent' => 'bg-emerald-500'],
        ['label' => __('S istaknutom slikom'), 'value' => number_format($stats['with_featured'], 0, ',', ' '), 'icon' => 'star', 'accent' => 'bg-amber-400'],
    ];
@endphp

<x-admin-ui::page>
    <x-admin-ui::page-header :title="__('Galerija')" :description="__('Centralni pregled svih galerija, slika i generiranih veličina.')">
        <x-slot:actions>
            <flux:button type="button" variant="primary" icon="plus" wire:click="createGallery">
                {{ __('Dodaj novu galeriju') }}
            </flux:button>
            <flux:button type="button" variant="ghost" icon="arrow-path" wire:click="regenerateAll" wire:loading.attr="disabled">
                {{ __('Regeneriraj sve') }}
            </flux:button>
            <flux:button type="button" variant="ghost" icon="cog-6-tooth" wire:click="openSettings">
                {{ __('Postavke') }}
            </flux:button>
        </x-slot:actions>
    </x-admin-ui::page-header>

    <x-admin-ui::stat-grid>
        @foreach ($cards as $card)
            <x-admin-ui::stat-card :label="$card['label']" :value="$card['value']" :accent="$card['accent']">
                <x-slot:icon>
                    <flux:icon :icon="$card['icon']" variant="micro" class="size-4" />
                </x-slot:icon>
            </x-admin-ui::stat-card>
        @endforeach
    </x-admin-ui::stat-grid>

    <x-admin-ui::toolbar-stack>
        <x-admin-ui::toolbar>
            <div class="relative w-full sm:w-80">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Pretraži galerije...')" />
            </div>
        </x-admin-ui::toolbar>
    </x-admin-ui::toolbar-stack>

    <x-admin-ui::panel loading loading-target="search,regenerateGallery,regenerateAll,saveSettings,createGallery,openGallery" loading-text="{{ __('Osvježavam galerije') }}">
        @if ($this->galleries->isEmpty())
            <x-admin-ui::empty-state
                :title="filled($search) ? __('Nema rezultata') : __('Još nema galerija')"
                :description="filled($search) ? __('Nijedna galerija ne odgovara trenutnoj pretrazi.') : __('Galerije će se pojaviti kada dodate fotografije na vozila, otkupe ili druge zapise.')"
            >
                <x-slot:icon>
                    <flux:icon icon="photo" class="size-6" />
                </x-slot:icon>
            </x-admin-ui::empty-state>
        @else
            <div class="admin-list-header grid-cols-[minmax(0,1fr)_8rem_10rem_10rem_9rem]">
                <div>{{ mb_strtoupper(__('Galerija')) }}</div>
                <div class="text-center">{{ mb_strtoupper(__('Slike')) }}</div>
                <div>{{ mb_strtoupper(__('Kreirano')) }}</div>
                <div>{{ mb_strtoupper(__('Ažurirano')) }}</div>
                <div class="text-right">{{ mb_strtoupper(__('Akcije')) }}</div>
            </div>

            @foreach ($this->galleries as $gallery)
                @php
                    $featured = $gallery->featuredOrFirstMedia();
                    $count = $gallery->getMedia($gallery->collection_name)->count();
                    $thumb = $featured?->getAvailableUrl(['admin_thumb', 'thumbnail', 'thumb']);
                @endphp
                <article wire:key="gallery-{{ $gallery->uuid }}" class="admin-list-row grid-cols-[minmax(0,1fr)_8rem_10rem_10rem_9rem] p-4 sm:p-6">
                    <div class="flex min-w-0 items-center gap-4">
                        <div class="size-20 shrink-0 overflow-hidden rounded-xl bg-zinc-100 ring-1 ring-zinc-950/5 dark:bg-zinc-900 dark:ring-white/10">
                            @if ($thumb)
                                <img src="{{ $thumb }}" alt="" class="h-full w-full object-cover" loading="lazy" />
                            @else
                                <div class="flex h-full w-full items-center justify-center text-zinc-300 dark:text-zinc-700">
                                    <flux:icon icon="photo" class="size-7" />
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0">
                            <a
                                href="{{ route('admin.galleries.edit', ['uuid' => $gallery->uuid]) }}"
                                wire:navigate
                                class="truncate text-left text-sm font-semibold text-zinc-950 underline-offset-4 hover:underline dark:text-white"
                            >
                                {{ $gallery->displayTitle() }}
                            </a>
                            <p class="mt-1 truncate text-[12px] leading-5 text-zinc-500 dark:text-zinc-400">{{ $gallery->ownerLabel() }}</p>
                            <p class="mt-1 text-[11px] font-medium uppercase tracking-[0.12em] text-zinc-400 dark:text-zinc-500">{{ $gallery->collection_name }}</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3 lg:justify-center">
                        <span class="text-sm font-medium text-zinc-400 lg:hidden">{{ __('Slike') }}</span>
                        <span class="font-semibold tabular-nums text-zinc-700 dark:text-zinc-200">{{ $count }}</span>
                    </div>

                    <div class="flex items-center justify-between gap-3 lg:block">
                        <span class="text-sm font-medium text-zinc-400 lg:hidden">{{ __('Kreirano') }}</span>
                        <span class="text-[13px] font-medium text-zinc-600 dark:text-zinc-300">{{ $gallery->created_at?->format('d.m.Y. H:i') }}</span>
                    </div>

                    <div class="flex items-center justify-between gap-3 lg:block">
                        <span class="text-sm font-medium text-zinc-400 lg:hidden">{{ __('Ažurirano') }}</span>
                        <span class="text-[13px] font-medium text-zinc-600 dark:text-zinc-300">{{ $gallery->updated_at?->diffForHumans() }}</span>
                    </div>

                    <div class="flex items-center justify-end gap-1">
                        <flux:tooltip :content="__('Ponovno generiraj veličine slika')">
                            <flux:button type="button" size="sm" variant="ghost" icon="arrow-path" wire:click="regenerateGallery('{{ $gallery->uuid }}')" wire:loading.attr="disabled" aria-label="{{ __('Regeneriraj galeriju') }}" />
                        </flux:tooltip>
                    </div>
                </article>
            @endforeach
        @endif
    </x-admin-ui::panel>

    @if ($this->galleries->hasPages())
        <flux:pagination :paginator="$this->galleries" />
    @endif

    <flux:modal name="gallery-settings" class="max-w-4xl">
        <form wire:submit="saveSettings" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Postavke galerije') }}</flux:heading>
                <flux:subheading class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Veličine su namjerno bliske WordPress standardima. Nakon promjene pokrenite regeneriranje postojećih slika.') }}</flux:subheading>
            </div>

            <div class="grid gap-3">
                @foreach ($sizeSettings as $name => $size)
                    <div class="grid gap-3 rounded-xl bg-zinc-50/70 p-4 ring-1 ring-zinc-950/5 dark:bg-zinc-900/80 dark:ring-white/10 md:grid-cols-[minmax(0,1fr)_7rem_7rem_9rem_6rem] md:items-end">
                        <flux:input wire:model="sizeSettings.{{ $name }}.label" :label="__('Naziv')" />
                        <flux:input wire:model="sizeSettings.{{ $name }}.width" :label="__('Širina')" type="number" min="1" />
                        <flux:input wire:model="sizeSettings.{{ $name }}.height" :label="__('Visina')" type="number" min="1" />
                        <flux:select wire:model="sizeSettings.{{ $name }}.fit" :label="__('Način')">
                            <flux:select.option value="contain">{{ __('Zadrži omjer') }}</flux:select.option>
                            <flux:select.option value="crop">{{ __('Crop') }}</flux:select.option>
                        </flux:select>
                        <div class="pb-2">
                            <flux:switch wire:model="sizeSettings.{{ $name }}.enabled" :label="__('Aktivno')" />
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex items-center justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Odustani') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">{{ __('Spremi postavke') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</x-admin-ui::page>
