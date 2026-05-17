@php
    $gallery = $this->gallery;
    $mediaItems = $this->mediaItems;
    $mediaCount = $mediaItems->count();
    $maxFiles = $this->maxFiles;
    $remainingSlots = $this->remainingSlots;
    $maxMb = number_format($this->maxFileSizeKb / 1024, 0, ',', ' ');
    $panelTitle = $title ?: __('Fotografije');
    $panelDescription = $description ?: __('Dodajte slike, postavite redoslijed i istaknutu fotografiju.');
    $featuredId = $gallery->featured_media_id ?: $mediaItems->first()?->id;
    $acceptedFormats = strtoupper(str_replace([',', '.'], [', ', ''], $this->acceptedMimes));
    $isFull = $remainingSlots <= 0;
    $selectedMediaCount = $this->selectedMediaCount;
    $seoCompleteCount = $this->seoCompleteCount;
    $lastRegeneratedAt = $gallery->lastRegeneratedAt();
    $queuedAt = $gallery->regenerationQueuedAt();
    $canUpload = $this->allowsGalleryAction('upload');
    $canUpdate = $this->allowsGalleryAction('update');
    $canDelete = $this->allowsGalleryAction('delete');
    $canRegenerate = $this->allowsGalleryAction('regenerate');
    $canSeo = $this->allowsGalleryAction('seo');
    $canBulkActions = $canDelete || $canRegenerate;
@endphp

<x-admin-ui::panel loading loading-target="uploads,saveUploads,deleteMedia,deleteSelectedMedia,regenerateSelectedMedia,reorderMedia,setFeaturedMedia,saveMediaMeta,regenerateGallery" loading-text="{{ __('Spremam promjene u galeriji...') }}">
    <x-admin-ui::panel-header :title="$panelTitle" :description="$panelDescription">
        @if (! $mediaItems->isEmpty() && ($canBulkActions || $canRegenerate))
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    @if ($canBulkActions)
                    <flux:tooltip :content="$selectMode ? __('Zatvori odabir') : __('Odaberi više fotografija')">
                        <flux:button size="sm" type="button" variant="ghost" icon="check-circle" wire:click="toggleSelectionMode" wire:loading.attr="disabled" aria-label="{{ $selectMode ? __('Zatvori odabir') : __('Odaberi više fotografija') }}" />
                    </flux:tooltip>
                    @endif
                    @if ($canRegenerate)
                    <flux:tooltip :content="__('Ponovno generiraj veličine slika')">
                        <flux:button size="sm" type="button" variant="ghost" icon="arrow-path" wire:click="regenerateGallery" wire:loading.attr="disabled" aria-label="{{ __('Ponovno generiraj veličine slika') }}" />
                    </flux:tooltip>
                    @endif
                </div>
            </x-slot:actions>
        @endif
    </x-admin-ui::panel-header>

    <div x-data class="px-6 pb-6 pt-5 sm:px-7 sm:pb-7">
        @if ($canUpload)
            <input id="gallery-upload-{{ $modalKey }}" x-ref="galleryUpload" wire:model="uploads" type="file" multiple accept="{{ $this->acceptedMimes }}" class="sr-only" @disabled($isFull) />
        @endif

        <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-[12px] leading-5">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400 dark:text-zinc-500">{{ __('Fotografije') }}</span>
                <span class="font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $mediaCount }}</span>
                <span class="tabular-nums text-zinc-400 dark:text-zinc-500">/ {{ $maxFiles }}</span>
                @if ($isFull)
                    <flux:badge size="sm" color="amber" class="ml-1">{{ __('Kapacitet popunjen') }}</flux:badge>
                @endif
                @if ($mediaCount > 0)
                    <span class="text-zinc-300 dark:text-zinc-700" aria-hidden="true">/</span>
                    <span class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400 dark:text-zinc-500">{{ __('SEO') }}</span>
                    <span class="font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $seoCompleteCount }}</span>
                    <span class="tabular-nums text-zinc-400 dark:text-zinc-500">/ {{ $mediaCount }}</span>
                @endif
                @if ($queuedAt)
                    <flux:badge size="sm" color="blue" icon="arrow-path" class="ml-1">{{ __('U obradi') }}</flux:badge>
                @elseif ($lastRegeneratedAt)
                    <span class="text-zinc-300 dark:text-zinc-700" aria-hidden="true">/</span>
                    <span class="text-zinc-500 dark:text-zinc-400">{{ __('Regenerirano :time', ['time' => $lastRegeneratedAt->diffForHumans()]) }}</span>
                @endif
            </div>
        </div>

        <div wire:loading.flex wire:target="uploads,regenerateGallery" class="mt-3 items-center gap-1.5 text-[12px] font-medium text-zinc-600 dark:text-zinc-300">
            <flux:icon icon="arrow-path" class="size-3.5 animate-spin" />
            <span wire:loading wire:target="uploads">{{ __('Dodajem slike u galeriju...') }}</span>
            <span wire:loading wire:target="regenerateGallery,regenerateSelectedMedia">{{ __('Pokrećem regeneriranje...') }}</span>
        </div>

        @error('uploads') <p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        @error('uploads.*') <p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

        @if ($selectMode && $canBulkActions)
            <div class="mt-5 flex flex-col gap-3 rounded-xl bg-zinc-50/80 p-3 ring-1 ring-zinc-950/5 dark:bg-zinc-900/80 dark:ring-white/10 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                    <flux:badge size="sm" icon="check-circle">{{ trans_choice('{0} Nema odabranih|{1} :count odabrana|[2,*] :count odabranih', $selectedMediaCount, ['count' => $selectedMediaCount]) }}</flux:badge>
                    <flux:button type="button" size="xs" variant="ghost" wire:click="selectAllMedia">{{ __('Odaberi sve') }}</flux:button>
                    <flux:button type="button" size="xs" variant="ghost" wire:click="clearSelectedMedia" :disabled="$selectedMediaCount === 0">{{ __('Očisti odabir') }}</flux:button>
                </div>

                <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                    @if ($canRegenerate)
                    <flux:button type="button" size="sm" variant="ghost" icon="arrow-path" wire:click="regenerateSelectedMedia" :disabled="$selectedMediaCount === 0" wire:loading.attr="disabled">
                        {{ __('Regeneriraj odabrane') }}
                    </flux:button>
                    @endif
                    @if ($canDelete)
                    <flux:button type="button" size="sm" variant="danger" icon="trash" wire:click="confirmDeleteSelectedMedia" :disabled="$selectedMediaCount === 0" wire:loading.attr="disabled">
                        {{ __('Obriši odabrane') }}
                    </flux:button>
                    @endif
                </div>
            </div>
        @endif

        <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @if ($canUpload)
            <button type="button" x-on:click.prevent="$refs.galleryUpload.click()" @disabled($isFull)
                class="group/add relative flex aspect-[4/3] w-full overflow-hidden rounded-xl border border-dashed border-zinc-300 bg-zinc-50/70 p-3 text-center ring-1 ring-zinc-950/0 transition duration-200 ease-out hover:-translate-y-0.5 hover:border-zinc-400 hover:bg-white hover:shadow-sm hover:shadow-zinc-950/5 hover:ring-zinc-950/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-950/10 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0 disabled:hover:shadow-none dark:border-white/10 dark:bg-zinc-900/60 dark:hover:border-white/20 dark:hover:bg-zinc-900 dark:hover:ring-white/10 dark:focus-visible:ring-white/10">
                <span class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_50%_0%,rgba(24,24,27,0.08),transparent_55%)] opacity-0 transition duration-200 ease-out group-hover/add:opacity-100 dark:bg-[radial-gradient(circle_at_50%_0%,rgba(255,255,255,0.12),transparent_55%)]"></span>

                <span class="relative flex h-full w-full flex-col items-center justify-center gap-2.5">
                    <span class="relative inline-flex size-12 items-center justify-center rounded-xl bg-white text-zinc-700 shadow-sm ring-1 ring-zinc-950/5 transition duration-200 ease-out group-hover/add:scale-105 group-hover/add:text-zinc-950 dark:bg-zinc-950 dark:text-zinc-200 dark:ring-white/10 dark:group-hover/add:text-white">
                        <span class="absolute -right-1 -top-1 inline-flex size-4 items-center justify-center rounded-full bg-zinc-950 text-white ring-2 ring-zinc-50 transition duration-200 ease-out group-hover/add:scale-110 dark:bg-white dark:text-zinc-950 dark:ring-zinc-900">
                            <flux:icon icon="plus" variant="micro" class="size-3" />
                        </span>
                        <svg viewBox="0 0 64 64" fill="none" aria-hidden="true" class="size-8 transition duration-200 ease-out group-hover/add:-translate-y-0.5">
                            <rect x="11" y="16" width="42" height="32" rx="6" class="fill-zinc-100 stroke-zinc-400/80 dark:fill-zinc-900 dark:stroke-zinc-500" stroke-width="2" />
                            <path d="M17 40l8-8 6 6 5-5 11 9" class="stroke-zinc-500 dark:stroke-zinc-400" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
                            <circle cx="39" cy="26" r="3.5" class="fill-zinc-400 dark:fill-zinc-500" />
                            <path d="M49 11v9M44.5 15.5h9" class="stroke-zinc-900 dark:stroke-white" stroke-width="2.6" stroke-linecap="round" />
                        </svg>
                    </span>

                    <span class="grid max-w-full gap-1">
                        <span class="text-[13px] font-semibold leading-5 text-zinc-950 dark:text-white">{{ __('Dodaj slike') }}</span>
                        <span class="text-[11px] font-medium leading-4 text-zinc-500 dark:text-zinc-400">{{ __('do :mb MB', ['mb' => $maxMb]) }}</span>
                        <span class="truncate text-[10px] font-semibold uppercase leading-4 tracking-[0.12em] text-zinc-400 dark:text-zinc-500">{{ $acceptedFormats }}</span>
                    </span>
                </span>
            </button>

            @endif

            @unless ($mediaItems->isEmpty())
                <div @if ($canUpdate) wire:sort="reorderMedia" @endif class="contents">
                @foreach ($mediaItems as $idx => $img)
                    @php
                        $isFeatured = (int) $featuredId === (int) $img->id;
                        $thumb = $img->getAvailableUrl(['admin_thumb', 'thumbnail', 'thumb']);
                        $alt = method_exists($img, 'altText') ? $img->altText($gallery->displayTitle()) : $img->name;
                        $hasSeo = $gallery->mediaHasSeo($img);
                    @endphp

                    <div wire:key="gallery-media-{{ $img->id }}" wire:sort:item="{{ $img->id }}" class="group/img relative overflow-hidden rounded-xl bg-zinc-100 ring-1 ring-zinc-950/5 transition duration-150 ease-out hover:ring-zinc-950/15 dark:bg-zinc-900 dark:ring-white/10 dark:hover:ring-white/20">
                        <img src="{{ $thumb }}" alt="{{ $alt }}" class="pointer-events-none aspect-[4/3] w-full object-cover select-none" loading="lazy" draggable="false" />

                        @if ($selectMode)
                            <label wire:sort:ignore class="absolute left-2 top-2 z-10 inline-flex size-6 items-center justify-center rounded-md bg-white/95 shadow-sm ring-1 ring-zinc-950/10 dark:bg-zinc-950/90 dark:ring-white/10">
                                <input type="checkbox" wire:model.live="selectedMediaIds" value="{{ $img->id }}" class="size-3.5 rounded border-zinc-300 text-zinc-950 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white dark:focus:ring-zinc-400" aria-label="{{ __('Odaberi fotografiju') }}" />
                            </label>
                        @endif

                        @if ($isFeatured)
                            <span @class([
                                'pointer-events-none absolute left-2 inline-flex items-center gap-1 rounded-md bg-white/95 px-1.5 py-0.5 text-[10px] font-semibold text-zinc-700 shadow-sm ring-1 ring-zinc-950/10 dark:bg-zinc-950/90 dark:text-zinc-200 dark:ring-white/10',
                                'top-9' => $selectMode,
                                'top-2' => ! $selectMode,
                            ])>
                                <flux:icon icon="star" variant="micro" class="size-3" />
                                {{ __('Istaknuta') }}
                            </span>
                        @endif

                        <span @class([
                            'pointer-events-none absolute bottom-2 left-2 inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-semibold shadow-sm ring-1 backdrop-blur',
                            'bg-emerald-50/95 text-emerald-700 ring-emerald-500/20 dark:bg-emerald-500/15 dark:text-emerald-200 dark:ring-emerald-500/20' => $hasSeo,
                            'bg-amber-50/95 text-amber-800 ring-amber-500/20 dark:bg-amber-500/15 dark:text-amber-200 dark:ring-amber-500/20' => ! $hasSeo,
                        ])>
                            <flux:icon :icon="$hasSeo ? 'check-circle' : 'exclamation-triangle'" variant="micro" class="size-3" />
                            {{ $hasSeo ? __('SEO') : __('Alt') }}
                        </span>

                        @if ($canUpdate)
                        <span wire:sort:handle class="absolute right-2 top-2 inline-flex size-5 cursor-grab items-center justify-center rounded-md bg-white/90 text-zinc-500 opacity-0 shadow-sm ring-1 ring-zinc-950/10 transition duration-150 ease-out group-hover/img:opacity-100 active:cursor-grabbing dark:bg-zinc-950/90 dark:text-zinc-400 dark:ring-white/10" aria-label="{{ __('Povucite za promjenu redoslijeda') }}">
                            <flux:icon icon="arrows-pointing-out" variant="micro" class="size-3" />
                        </span>
                        @endif

                        @if ($canUpdate || $canSeo || $canDelete)
                        <div wire:sort:ignore class="absolute inset-x-0 bottom-0 flex items-center justify-end gap-1 bg-gradient-to-t from-black/65 via-black/30 to-transparent p-1.5 opacity-0 transition duration-150 ease-out group-hover/img:opacity-100 group-focus-within/img:opacity-100">
                            @if ($canUpdate && ! $isFeatured)
                                <flux:tooltip :content="__('Postavi za istaknutu sliku')">
                                    <flux:button size="xs" variant="ghost" icon="star" wire:click="setFeaturedMedia({{ $img->id }})" aria-label="{{ __('Postavi za istaknutu sliku') }}" class="!text-white hover:!bg-white/15" />
                                </flux:tooltip>
                            @endif
                            @if ($canSeo)
                            <flux:tooltip :content="__('Uredi SEO podatke slike')">
                                <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="editMedia({{ $img->id }})" aria-label="{{ __('Uredi SEO podatke slike') }}" class="!text-white hover:!bg-white/15" />
                            </flux:tooltip>
                            @endif
                            @if ($canDelete)
                            <flux:tooltip :content="__('Obriši fotografiju')">
                                <flux:button size="xs" variant="ghost" icon="trash" wire:click="confirmDeleteMedia({{ $img->id }})" aria-label="{{ __('Obriši fotografiju') }}" class="!text-white hover:!bg-white/15" />
                            </flux:tooltip>
                            @endif
                        </div>
                        @endif
                    </div>
                @endforeach
                </div>
            @endunless
        </div>
    </div>

    <flux:modal name="{{ $this->metaModalName() }}" class="max-w-4xl">
        <div class="space-y-7">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start">
                <div>
                    <flux:heading size="lg">{{ __('SEO podaci fotografije') }}</flux:heading>
                    <flux:subheading class="mt-1 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                        {{ __('Dodajte kratke i korisne podatke koji pomažu pristupačnosti, organizaciji i prikazu fotografije.') }}
                    </flux:subheading>
                </div>

                <div class="rounded-xl bg-zinc-50/80 p-4 text-[13px] leading-5 ring-1 ring-zinc-950/5 dark:bg-zinc-900/80 dark:ring-white/10">
                    <div class="flex items-start gap-2.5">
                        <flux:icon icon="information-circle" variant="micro" class="mt-0.5 size-4 shrink-0 text-zinc-400 dark:text-zinc-500" />
                        <p class="text-zinc-600 dark:text-zinc-300">
                            {{ __('Alt tekst opisuje što je na slici. Koriste ga čitači ekrana i prikazuje se kada se slika ne može učitati.') }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid gap-6">
                <div class="grid gap-2">
                    <flux:input wire:model="mediaForm.alt" :label="__('Alt tekst')" :placeholder="__('Kratko opišite sadržaj slike')" />
                    <p class="text-[12px] leading-5 text-zinc-500 dark:text-zinc-400">
                        {{ __('Napišite jednu jasnu rečenicu. Ne morate pisati “slika od” ili “fotografija”.') }}
                    </p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <flux:input wire:model="mediaForm.title" :label="__('Naslov slike')" :placeholder="__('Kratki naziv slike')" />
                        <p class="text-[12px] leading-5 text-zinc-500 dark:text-zinc-400">
                            {{ __('Naziv za lakše prepoznavanje slike u administraciji ili galeriji.') }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <flux:input wire:model="mediaForm.credit" :label="__('Autor / kredit')" :placeholder="__('Ime autora ili izvora')" />
                        <p class="text-[12px] leading-5 text-zinc-500 dark:text-zinc-400">
                            {{ __('Upišite autora ili izvor ako želite zadržati evidenciju o porijeklu slike.') }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-5 lg:grid-cols-2">
                    <div class="grid gap-2">
                        <flux:textarea wire:model="mediaForm.caption" :label="__('Opis uz sliku')" rows="4" :placeholder="__('Tekst koji se može prikazati uz sliku')" />
                        <p class="text-[12px] leading-5 text-zinc-500 dark:text-zinc-400">
                            {{ __('Kratak opis namijenjen korisniku, ako se prikazuje na stranici.') }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <flux:textarea wire:model="mediaForm.description" :label="__('Interni SEO opis')" rows="4" :placeholder="__('Dodatni opis slike')" />
                        <p class="text-[12px] leading-5 text-zinc-500 dark:text-zinc-400">
                            {{ __('Dulji interni opis za dodatni kontekst. Ne mora biti javno prikazan.') }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <flux:input wire:model="mediaForm.source_url" :label="__('Izvor URL')" type="url" :placeholder="__('https://...')" />
                        <p class="text-[12px] leading-5 text-zinc-500 dark:text-zinc-400">
                            {{ __('Link na originalni izvor slike, ako postoji.') }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <flux:input wire:model="mediaForm.license" :label="__('Licenca')" :placeholder="__('npr. vlastita slika, CC BY, kupljena licenca')" />
                        <p class="text-[12px] leading-5 text-zinc-500 dark:text-zinc-400">
                            {{ __('Kratka napomena o pravima korištenja slike.') }}
                        </p>
                    </div>
                </div>

                <div class="rounded-xl bg-zinc-50/70 p-4 ring-1 ring-zinc-950/5 dark:bg-zinc-900/80 dark:ring-white/10">
                    <flux:checkbox wire:model="mediaForm.is_decorative" :label="__('Dekorativna slika')" />
                    <p class="mt-2 text-[12px] leading-5 text-zinc-500 dark:text-zinc-400">
                        {{ __('Označite samo ako slika nema važno značenje i služi isključivo kao ukras.') }}
                    </p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Odustani') }}</flux:button>
                </flux:modal.close>
                <flux:button type="button" variant="primary" icon="check" wire:click="saveMediaMeta">{{ __('Spremi podatke') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="{{ $this->deleteModalName() }}" class="max-w-lg" @cancel="$wire.cancelDeleteMedia()">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Obrisati fotografiju?') }}</flux:heading>
                <flux:subheading class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Ova radnja se ne može poništiti. Fotografija se trajno uklanja iz galerije.') }}</flux:subheading>
            </div>

            @if ($this->pendingDeleteMedia)
                <div class="flex items-center gap-4 rounded-xl bg-zinc-50/70 p-3 ring-1 ring-zinc-950/5 dark:bg-zinc-900/80 dark:ring-white/10">
                    <div class="relative size-20 shrink-0 overflow-hidden rounded-lg bg-zinc-100 ring-1 ring-zinc-950/5 dark:bg-zinc-900 dark:ring-white/10">
                        <img src="{{ $this->pendingDeleteMedia->getAvailableUrl(['admin_thumb', 'thumbnail', 'thumb']) }}" alt="" class="h-full w-full object-cover" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[14px] font-semibold leading-5 text-zinc-950 dark:text-white">{{ $this->pendingDeleteMedia->name }}</p>
                        <p class="mt-0.5 text-[12px] leading-5 text-zinc-500 dark:text-zinc-400">{{ $gallery->displayTitle() }}</p>
                    </div>
                </div>
            @endif

            <div class="flex items-center justify-end gap-2">
                <flux:button variant="ghost" wire:click="cancelDeleteMedia">{{ __('Odustani') }}</flux:button>
                <flux:button variant="danger" icon="trash" wire:click="deleteMedia">{{ __('Obriši fotografiju') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="{{ $this->bulkDeleteModalName() }}" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Obrisati odabrane fotografije?') }}</flux:heading>
                <flux:subheading class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Ova radnja trajno uklanja odabrane fotografije iz galerije.') }}
                </flux:subheading>
            </div>

            <div class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200 dark:bg-red-950/30 dark:text-red-300 dark:ring-red-900/50">
                {{ trans_choice('{1} Odabrana je :count fotografija.|[2,*] Odabrano je :count fotografija.', $selectedMediaCount, ['count' => $selectedMediaCount]) }}
            </div>

            <div class="flex items-center justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Odustani') }}</flux:button>
                </flux:modal.close>
                <flux:button type="button" variant="danger" icon="trash" wire:click="deleteSelectedMedia" wire:loading.attr="disabled" wire:target="deleteSelectedMedia">
                    {{ __('Trajno obriši') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</x-admin-ui::panel>
