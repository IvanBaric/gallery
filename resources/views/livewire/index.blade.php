@php
    $stats = $this->stats;
    $cards = [
        ['label' => __('Galerije'), 'value' => number_format($stats['galleries'], 0, ',', ' '), 'icon' => 'photo', 'accent' => 'bg-zinc-900 dark:bg-white'],
        ['label' => __('Fotografije'), 'value' => number_format($stats['images'], 0, ',', ' '), 'icon' => 'squares-2x2', 'accent' => 'bg-emerald-500'],
        ['label' => __('Prazne'), 'value' => number_format($stats['empty'], 0, ',', ' '), 'icon' => 'archive-box', 'accent' => 'bg-sky-500'],
        ['label' => __('S istaknutom slikom'), 'value' => number_format($stats['with_featured'], 0, ',', ' '), 'icon' => 'star', 'accent' => 'bg-amber-400'],
    ];
    $regenerationSummary = $this->regenerationSummary;
    $canCreate = $this->allowsGalleryAction('create');
    $canRegenerate = $this->allowsGalleryAction('regenerate');
    $canSettings = $this->allowsGalleryAction('settings');
    $canUpload = $this->allowsGalleryAction('upload');
@endphp

<x-admin-ui::page>
    <x-admin-ui::page-header :title="__('Galerija')" :description="__('Centralni pregled svih galerija, slika i generiranih veličina.')">
        <x-slot:actions>
            @if ($canCreate)
            <flux:button type="button" variant="primary" icon="plus" wire:click="openCreateGalleryModal">
                {{ __('Dodaj novu galeriju') }}
            </flux:button>
            @endif
            @if ($canRegenerate)
            <flux:button type="button" variant="ghost" icon="arrow-path" wire:click="openRegenerateAllConfirmation" wire:loading.attr="disabled">
                {{ __('Regeneriraj sve') }}
            </flux:button>
            @endif
            @if ($canSettings)
            <flux:button type="button" variant="ghost" icon="cog-6-tooth" wire:click="openSettings">
                {{ __('Postavke') }}
            </flux:button>
            @endif
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

        <x-admin-ui::filter-tabs :items="$this->filterOptions" :active="$filter" />
    </x-admin-ui::toolbar-stack>

    <x-admin-ui::panel loading loading-target="search,setFilter,regenerateAll,saveSettings,createGallery" loading-text="{{ __('Ažuriram pregled galerija...') }}">
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
            <div class="admin-list-header grid-cols-[minmax(0,1fr)_7rem_9rem_9rem_10rem]">
                <div>{{ mb_strtoupper(__('Galerija')) }}</div>
                <div class="flex justify-center">
                    <button type="button" wire:click="sortBy('images_count')" class="inline-flex items-center justify-center gap-1 transition duration-150 ease-out hover:text-zinc-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400 dark:hover:text-zinc-200">
                        {{ mb_strtoupper(__('Slike')) }}
                        @if ($sortField === 'images_count')
                            <flux:icon :icon="$sortDirection === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" class="size-3" />
                        @endif
                    </button>
                </div>
                <div>
                    <button type="button" wire:click="sortBy('created_at')" class="inline-flex items-center gap-1 transition duration-150 ease-out hover:text-zinc-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400 dark:hover:text-zinc-200">
                        {{ mb_strtoupper(__('Kreirano')) }}
                        @if ($sortField === 'created_at')
                            <flux:icon :icon="$sortDirection === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" class="size-3" />
                        @endif
                    </button>
                </div>
                <div>
                    <button type="button" wire:click="sortBy('updated_at')" class="inline-flex items-center gap-1 transition duration-150 ease-out hover:text-zinc-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400 dark:hover:text-zinc-200">
                        {{ mb_strtoupper(__('Ažurirano')) }}
                        @if ($sortField === 'updated_at')
                            <flux:icon :icon="$sortDirection === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" class="size-3" />
                        @endif
                    </button>
                </div>
                <div>{{ mb_strtoupper(__('Regenerirano')) }}</div>
            </div>

            @foreach ($this->galleries as $gallery)
                @php
                    $featured = $gallery->featuredOrFirstMedia();
                    $count = $gallery->getMedia($gallery->collection_name)->count();
                    $thumb = $featured?->getAvailableUrl(['admin_thumb', 'thumbnail', 'thumb']);
                    $lastRegeneratedAt = $gallery->lastRegeneratedAt();
                    $queuedAt = $gallery->regenerationQueuedAt();
                @endphp
                <article wire:key="gallery-{{ $gallery->uuid }}" class="admin-list-row grid-cols-[minmax(0,1fr)_7rem_9rem_9rem_10rem] p-4 sm:p-6">
                    <div class="flex min-w-0 items-center gap-4">
                        <div class="relative h-20 w-28 shrink-0 overflow-hidden rounded-xl bg-zinc-100 ring-1 ring-zinc-950/5 dark:bg-zinc-900 dark:ring-white/10">
                            @if ($thumb)
                                <img src="{{ $thumb }}" alt="" class="h-full w-full object-cover" loading="lazy" />
                            @else
                                <div class="flex h-full w-full items-center justify-center text-zinc-300 dark:text-zinc-700">
                                    <flux:icon icon="photo" class="size-7" />
                                </div>
                            @endif

                            @if ($count > 0)
                                <span class="absolute bottom-1 right-1 inline-flex items-center gap-1 rounded-md bg-zinc-950/75 px-2 py-1 text-xs font-semibold tabular-nums text-white shadow-sm ring-1 ring-white/10 backdrop-blur" aria-label="{{ trans_choice('{1} :count fotografija|[2,4] :count fotografije|[5,*] :count fotografija', $count, ['count' => $count]) }}">
                                    <flux:icon icon="photo" variant="micro" class="size-3" />
                                    {{ $count }}
                                </span>
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
                            <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                <span class="text-[11px] font-medium uppercase tracking-[0.12em] text-zinc-400 dark:text-zinc-500">{{ $gallery->collection_name }}</span>
                                <span class="text-zinc-300 dark:text-zinc-700" aria-hidden="true">/</span>
                                <span class="text-[11px] font-medium text-zinc-400 dark:text-zinc-500">{{ $gallery->ownerTypeLabel() }}</span>
                            </div>
                            @if ($count === 0)
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <flux:badge size="sm" icon="photo">{{ __('Prazna galerija') }}</flux:badge>
                                    @if ($canUpload)
                                        <a href="{{ route('admin.galleries.edit', ['uuid' => $gallery->uuid]) }}" wire:navigate class="text-[12px] font-semibold text-zinc-700 underline-offset-4 hover:underline dark:text-zinc-200">{{ __('Dodaj slike') }}</a>
                                    @endif
                                </div>
                            @elseif (! $gallery->featured_media_id)
                                <div class="mt-2">
                                    <flux:badge size="sm" color="amber" icon="star">{{ __('Bez istaknute slike') }}</flux:badge>
                                </div>
                            @endif
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

                    <div class="flex items-center justify-between gap-3 lg:block">
                        <span class="text-sm font-medium text-zinc-400 lg:hidden">{{ __('Regenerirano') }}</span>
                        @if ($queuedAt)
                            <flux:badge size="sm" color="blue" icon="arrow-path">{{ __('U obradi') }}</flux:badge>
                        @elseif ($lastRegeneratedAt)
                            <span class="text-[13px] font-medium text-zinc-600 dark:text-zinc-300">{{ $lastRegeneratedAt->diffForHumans() }}</span>
                        @else
                            <x-admin-ui::empty-value />
                        @endif
                    </div>

                </article>
            @endforeach
        @endif
    </x-admin-ui::panel>

    @if ($this->galleries->hasPages())
        <flux:pagination :paginator="$this->galleries" />
    @endif

    <flux:modal name="gallery-create" class="max-w-lg">
        <form wire:submit="createGallery" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Nova galerija') }}</flux:heading>
                <flux:subheading class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Unesite naziv prije otvaranja prazne galerije.') }}</flux:subheading>
            </div>

            <flux:input
                wire:model="createForm.title"
                :label="__('Naziv galerije')"
                :placeholder="__('npr. Proljetna kolekcija')"
                autofocus
                required
            />

            <div class="flex items-center justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Odustani') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="createGallery">
                    {{ __('Kreiraj galeriju') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="gallery-regenerate-all-confirm" class="max-w-xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Regenerirati sve galerije?') }}</flux:heading>
                <flux:subheading class="mt-1 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                    {{ __('Regeneriranje ponovno izrađuje sve definirane veličine slika za postojeće fotografije. Koristite ga nakon promjene dimenzija, crop načina ili kada neka generirana veličina nedostaje. Originalne slike i SEO podaci se ne mijenjaju.') }}
                </flux:subheading>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-zinc-50/70 px-4 py-3 ring-1 ring-zinc-950/5 dark:bg-zinc-900/80 dark:ring-white/10">
                    <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400 dark:text-zinc-500">{{ __('Galerije') }}</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-zinc-950 dark:text-white">{{ number_format($regenerationSummary['galleries'], 0, ',', ' ') }}</p>
                </div>
                <div class="rounded-xl bg-zinc-50/70 px-4 py-3 ring-1 ring-zinc-950/5 dark:bg-zinc-900/80 dark:ring-white/10">
                    <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400 dark:text-zinc-500">{{ __('Fotografije') }}</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-zinc-950 dark:text-white">{{ number_format($regenerationSummary['images'], 0, ',', ' ') }}</p>
                </div>
                <div class="rounded-xl bg-zinc-50/70 px-4 py-3 ring-1 ring-zinc-950/5 dark:bg-zinc-900/80 dark:ring-white/10">
                    <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400 dark:text-zinc-500">{{ __('Zadnji put') }}</p>
                    <p class="mt-1 text-sm font-semibold text-zinc-950 dark:text-white">
                        {{ $regenerationSummary['last_regenerated_at'] ? $regenerationSummary['last_regenerated_at']->diffForHumans() : __('Još nije pokrenuto') }}
                    </p>
                </div>
            </div>

            @if ($regenerationSummary['queued'] > 0)
                <div class="rounded-xl bg-blue-50 px-4 py-3 text-sm text-blue-700 ring-1 ring-blue-200 dark:bg-blue-950/30 dark:text-blue-300 dark:ring-blue-900/50">
                    {{ trans_choice('{1} Jedna galerija je već u obradi.|[2,*] :count galerija je već u obradi.', $regenerationSummary['queued'], ['count' => $regenerationSummary['queued']]) }}
                </div>
            @endif

            <div class="flex items-center justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Odustani') }}</flux:button>
                </flux:modal.close>
                <flux:button type="button" variant="primary" icon="arrow-path" wire:click="regenerateAll" wire:loading.attr="disabled" wire:target="regenerateAll" :disabled="$regenerationSummary['galleries'] === 0">
                    <span wire:loading.remove wire:target="regenerateAll">{{ __('Pokreni regeneriranje') }}</span>
                    <span wire:loading wire:target="regenerateAll">{{ __('Pokrećem...') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

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
