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
@endphp

<x-admin-ui::panel loading loading-target="uploads,saveUploads,deleteMedia,reorderMedia,setFeaturedMedia,saveMediaMeta,regenerateGallery" loading-text="{{ __('Spremam promjene u galeriji...') }}">
    <x-admin-ui::panel-header :title="$panelTitle" :description="$panelDescription">
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @unless ($mediaItems->isEmpty())
                    <flux:tooltip :content="__('Ponovno generiraj veličine slika')">
                        <flux:button size="sm" type="button" variant="ghost" icon="arrow-path" wire:click="regenerateGallery" wire:loading.attr="disabled" aria-label="{{ __('Ponovno generiraj veličine slika') }}" />
                    </flux:tooltip>
                @endunless

                <flux:button size="sm" type="button" variant="primary" icon="plus" :disabled="$isFull" x-on:click.prevent="$refs.galleryUpload.click()">
                    {{ __('Dodaj slike') }}
                </flux:button>
            </div>
        </x-slot:actions>
    </x-admin-ui::panel-header>

    <div x-data class="px-6 pb-6 pt-5 sm:px-7 sm:pb-7">
        <input id="gallery-upload-{{ $modalKey }}" x-ref="galleryUpload" wire:model="uploads" type="file" multiple accept="{{ $this->acceptedMimes }}" class="sr-only" @disabled($isFull) />

        <div class="flex flex-wrap items-center justify-between gap-x-5 gap-y-2 text-[12px] leading-5">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400 dark:text-zinc-500">{{ __('Fotografije') }}</span>
                <span class="font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $mediaCount }}</span>
                <span class="tabular-nums text-zinc-400 dark:text-zinc-500">/ {{ $maxFiles }}</span>
                @if ($isFull)
                    <flux:badge size="sm" color="amber" class="ml-1">{{ __('Kapacitet popunjen') }}</flux:badge>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-zinc-500 dark:text-zinc-400">
                <span>{{ __('do :mb MB', ['mb' => $maxMb]) }}</span>
                <span class="text-zinc-300 dark:text-zinc-600" aria-hidden="true">·</span>
                <span class="font-medium text-zinc-600 dark:text-zinc-300">{{ $acceptedFormats }}</span>
            </div>
        </div>

        <div wire:loading.flex wire:target="uploads,regenerateGallery" class="mt-3 items-center gap-1.5 text-[12px] font-medium text-zinc-600 dark:text-zinc-300">
            <flux:icon icon="arrow-path" class="size-3.5 animate-spin" />
            <span wire:loading wire:target="uploads">{{ __('Dodajem slike u galeriju...') }}</span>
            <span wire:loading wire:target="regenerateGallery">{{ __('Regeneriram veličine slika...') }}</span>
        </div>

        @error('uploads') <p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        @error('uploads.*') <p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

        @if ($mediaItems->isEmpty())
            <button type="button" x-on:click.prevent="$refs.galleryUpload.click()" @disabled($isFull)
                class="group/dz mt-5 flex w-full flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-zinc-300 bg-zinc-50/50 px-6 py-14 text-center transition duration-150 ease-out hover:border-zinc-400 hover:bg-zinc-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-950/10 disabled:cursor-not-allowed disabled:opacity-60 dark:border-white/10 dark:bg-zinc-900/40 dark:hover:border-white/20 dark:hover:bg-zinc-900/70 dark:focus-visible:ring-white/10">
                <span class="inline-flex size-11 items-center justify-center rounded-full bg-white text-zinc-500 ring-1 ring-zinc-950/5 transition duration-150 ease-out group-hover/dz:text-zinc-900 dark:bg-zinc-900 dark:text-zinc-400 dark:ring-white/10 dark:group-hover/dz:text-white">
                    <flux:icon icon="photo" class="size-5" />
                </span>
                <div>
                    <p class="text-[14px] font-semibold leading-5 text-zinc-950 dark:text-white">{{ __('Još nema fotografija') }}</p>
                    <p class="mt-1 text-[12px] leading-5 text-zinc-500 dark:text-zinc-400">{{ __('Kliknite za odabir slika ili koristite gumb „Dodaj slike“.') }}</p>
                </div>
            </button>
        @else
            <div wire:sort="reorderMedia" class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                @foreach ($mediaItems as $idx => $img)
                    @php
                        $isFeatured = (int) $featuredId === (int) $img->id;
                        $thumb = $img->getAvailableUrl(['admin_thumb', 'thumbnail', 'thumb']);
                        $alt = method_exists($img, 'altText') ? $img->altText($gallery->displayTitle()) : $img->name;
                    @endphp

                    <div wire:key="gallery-media-{{ $img->id }}" wire:sort:item="{{ $img->id }}" class="group/img relative overflow-hidden rounded-xl bg-zinc-100 ring-1 ring-zinc-950/5 transition duration-150 ease-out hover:ring-zinc-950/15 dark:bg-zinc-900 dark:ring-white/10 dark:hover:ring-white/20">
                        <img src="{{ $thumb }}" alt="{{ $alt }}" class="pointer-events-none aspect-[4/3] w-full object-cover select-none" loading="lazy" draggable="false" />

                        @if ($isFeatured)
                            <span class="pointer-events-none absolute left-2 top-2 inline-flex items-center gap-1 rounded-md bg-white/95 px-1.5 py-0.5 text-[10px] font-semibold text-zinc-700 shadow-sm ring-1 ring-zinc-950/10 dark:bg-zinc-950/90 dark:text-zinc-200 dark:ring-white/10">
                                <flux:icon icon="star" variant="micro" class="size-3" />
                                {{ __('Istaknuta') }}
                            </span>
                        @endif

                        <span wire:sort:handle class="absolute right-2 top-2 inline-flex size-5 cursor-grab items-center justify-center rounded-md bg-white/90 text-zinc-500 opacity-0 shadow-sm ring-1 ring-zinc-950/10 transition duration-150 ease-out group-hover/img:opacity-100 active:cursor-grabbing dark:bg-zinc-950/90 dark:text-zinc-400 dark:ring-white/10" aria-label="{{ __('Povucite za promjenu redoslijeda') }}">
                            <flux:icon icon="arrows-pointing-out" variant="micro" class="size-3" />
                        </span>

                        <div wire:sort:ignore class="absolute inset-x-0 bottom-0 flex items-center justify-end gap-1 bg-gradient-to-t from-black/65 via-black/30 to-transparent p-1.5 opacity-0 transition duration-150 ease-out group-hover/img:opacity-100 group-focus-within/img:opacity-100">
                            @unless ($isFeatured)
                                <flux:tooltip :content="__('Postavi za istaknutu sliku')">
                                    <flux:button size="xs" variant="ghost" icon="star" wire:click="setFeaturedMedia({{ $img->id }})" aria-label="{{ __('Postavi za istaknutu sliku') }}" class="!text-white hover:!bg-white/15" />
                                </flux:tooltip>
                            @endunless
                            <flux:tooltip :content="__('Uredi SEO podatke slike')">
                                <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="editMedia({{ $img->id }})" aria-label="{{ __('Uredi SEO podatke slike') }}" class="!text-white hover:!bg-white/15" />
                            </flux:tooltip>
                            <flux:tooltip :content="__('Obriši fotografiju')">
                                <flux:button size="xs" variant="ghost" icon="trash" wire:click="confirmDeleteMedia({{ $img->id }})" aria-label="{{ __('Obriši fotografiju') }}" class="!text-white hover:!bg-white/15" />
                            </flux:tooltip>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <flux:modal name="{{ $this->metaModalName() }}" class="max-w-2xl">
        <form wire:submit="saveMediaMeta" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Podaci fotografije') }}</flux:heading>
                <flux:subheading class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Alt tekst, naslov i opis koriste se za SEO, pristupačnost i javni prikaz galerije.') }}</flux:subheading>
            </div>

            <div class="grid gap-5">
                <flux:input wire:model="mediaForm.alt" :label="__('Alt tekst')" :placeholder="__('Opišite što je na slici')" />
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model="mediaForm.title" :label="__('Naslov slike')" />
                    <flux:input wire:model="mediaForm.credit" :label="__('Autor / kredit')" />
                </div>
                <flux:textarea wire:model="mediaForm.caption" :label="__('Opis uz sliku')" rows="3" />
                <flux:textarea wire:model="mediaForm.description" :label="__('Interni SEO opis')" rows="4" />
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model="mediaForm.source_url" :label="__('Izvor URL')" type="url" />
                    <flux:input wire:model="mediaForm.license" :label="__('Licenca')" />
                </div>
                <flux:checkbox wire:model="mediaForm.is_decorative" :label="__('Dekorativna slika')" />
            </div>

            <div class="flex items-center justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Odustani') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">{{ __('Spremi podatke') }}</flux:button>
            </div>
        </form>
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
</x-admin-ui::panel>
