@php
    $gallery = $this->gallery;
    $mediaItems = $this->mediaItems;
    $mediaCount = $mediaItems->count();
    $maxFiles = $this->maxFiles;
    $remainingSlots = $this->remainingSlots;
    $maxMb = number_format($this->maxFileSizeKb / 1024, 0, ',', ' ');
    $panelTitle = $title ?: __('Fotografije');
    $panelDescription = $description ?: __('Do :max fotografija, maksimalno :mb MB svaka.', ['max' => $maxFiles, 'mb' => $maxMb]);
    $featuredId = $gallery->featured_media_id ?: $mediaItems->first()?->id;
@endphp

<x-admin-ui::panel loading loading-target="uploads,saveUploads,deleteMedia,reorderMedia,setFeaturedMedia,saveMediaMeta,regenerateGallery" loading-text="{{ __('Osvježavam galeriju') }}">
    <x-admin-ui::panel-header :title="$panelTitle" :description="$panelDescription">
        <x-slot:actions>
            <div class="flex flex-wrap items-center justify-end gap-3">
                <div class="flex shrink-0 items-center gap-3">
                    <span class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400 dark:text-zinc-500">{{ __('Iskorišteno') }}</span>
                    <span class="text-sm font-semibold tabular-nums text-zinc-700 dark:text-zinc-200">{{ $mediaCount }}<span class="font-normal text-zinc-400 dark:text-zinc-500"> / {{ $maxFiles }}</span></span>
                    <div class="h-1 w-20 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800/80">
                        <div class="h-full rounded-full bg-zinc-900/80 transition-all duration-200 ease-out dark:bg-white/80" style="width: {{ $maxFiles > 0 ? min(100, ($mediaCount / $maxFiles) * 100) : 0 }}%"></div>
                    </div>
                </div>

                <flux:tooltip :content="__('Ponovno generiraj veličine slika za ovu galeriju')">
                    <flux:button type="button" size="sm" variant="ghost" icon="arrow-path" wire:click="regenerateGallery" wire:loading.attr="disabled">
                        {{ __('Regeneriraj') }}
                    </flux:button>
                </flux:tooltip>
            </div>
        </x-slot:actions>
    </x-admin-ui::panel-header>

    <div class="p-6">
        <label for="gallery-upload-{{ $modalKey }}" @class([
            'group relative flex flex-col items-center justify-center gap-3 rounded-2xl bg-zinc-50/70 px-6 py-10 text-center ring-1 ring-dashed ring-zinc-950/10 transition duration-150 ease-out focus-within:bg-zinc-50 focus-within:ring-zinc-950/20 dark:bg-zinc-900/70 dark:ring-white/10 dark:focus-within:bg-zinc-900 dark:focus-within:ring-white/20',
            'cursor-pointer hover:bg-zinc-50 hover:ring-zinc-950/20 dark:hover:bg-zinc-900 dark:hover:ring-white/20' => $remainingSlots > 0,
            'cursor-not-allowed opacity-60' => $remainingSlots <= 0,
        ])>
            <div class="relative flex size-11 shrink-0 items-center justify-center rounded-2xl bg-white text-zinc-400 shadow-sm ring-1 ring-zinc-950/5 transition duration-150 ease-out group-hover:text-zinc-700 dark:bg-zinc-950 dark:text-zinc-500 dark:ring-white/10 dark:group-hover:text-zinc-200">
                <flux:icon icon="arrow-up-tray" variant="micro" class="size-5" wire:loading.remove wire:target="uploads" />
                <flux:icon icon="arrow-path" variant="micro" class="size-5 animate-spin" wire:loading wire:target="uploads" />
            </div>

            <div class="space-y-1 text-center">
                <p class="text-sm font-semibold text-zinc-950 dark:text-white">
                    @if ($remainingSlots > 0)
                        <span wire:loading.remove wire:target="uploads">{{ __('Kliknite za odabir ili povucite fotografije') }}</span>
                        <span wire:loading wire:target="uploads">{{ __('Učitavanje...') }}</span>
                    @else
                        {{ __('Galerija je popunjena') }}
                    @endif
                </p>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ strtoupper(str_replace(',', ', ', str_replace('.', '', $this->acceptedMimes))) }} · {{ __('do :mb MB · preostalo :n', ['mb' => $maxMb, 'n' => $remainingSlots]) }}
                </p>
            </div>

            <input id="gallery-upload-{{ $modalKey }}" wire:model="uploads" type="file" multiple accept="{{ $this->acceptedMimes }}" class="sr-only" @disabled($remainingSlots <= 0) />
        </label>

        @error('uploads') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        @error('uploads.*') <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

        @if ($mediaItems->isEmpty())
            <x-admin-ui::empty-state
                :title="__('Još nema fotografija')"
                :description="__('Dodajte fotografije i odmah ih uredite, poredajte ili postavite kao istaknute.')"
                class="mt-6 min-h-[14rem] rounded-2xl bg-zinc-50/60 ring-1 ring-zinc-950/5 dark:bg-zinc-900/70 dark:ring-white/10"
            >
                <x-slot:icon>
                    <flux:icon icon="photo" class="size-6" />
                </x-slot:icon>
            </x-admin-ui::empty-state>
        @else
            <div class="mt-6">
                <div class="mb-2.5 flex items-center justify-between gap-3">
                    <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400 dark:text-zinc-500">{{ __('Spremljene fotografije') }}</p>
                    <p class="hidden text-[12px] leading-5 text-zinc-400 dark:text-zinc-500 sm:block">{{ __('Povucite za promjenu redoslijeda') }}</p>
                </div>

                <div wire:sort="reorderMedia" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($mediaItems as $idx => $img)
                        @php
                            $isFeatured = (int) $featuredId === (int) $img->id;
                            $thumb = $img->getAvailableUrl(['admin_thumb', 'thumbnail', 'thumb']);
                            $alt = method_exists($img, 'altText') ? $img->altText($gallery->displayTitle()) : $img->name;
                        @endphp

                        <div wire:key="gallery-media-{{ $img->id }}" wire:sort:item="{{ $img->id }}" class="group/img relative cursor-grab overflow-hidden rounded-xl ring-1 ring-zinc-950/5 transition duration-150 ease-out hover:ring-zinc-950/15 active:cursor-grabbing dark:ring-white/10 dark:hover:ring-white/20">
                            <img src="{{ $thumb }}" alt="{{ $alt }}" class="pointer-events-none aspect-[4/3] w-full object-cover select-none" loading="lazy" draggable="false" />

                            @if ($isFeatured)
                                <span class="pointer-events-none absolute left-2 top-2 inline-flex items-center gap-1 rounded-md bg-white/95 px-1.5 py-0.5 text-[10px] font-semibold text-zinc-700 shadow-sm ring-1 ring-zinc-950/10 dark:bg-zinc-950/90 dark:text-zinc-200 dark:ring-white/10">
                                    <flux:icon icon="star" variant="micro" class="size-3" />
                                    {{ __('Istaknuta') }}
                                </span>
                            @endif

                            <span class="pointer-events-none absolute right-2 top-2 inline-flex size-5 items-center justify-center rounded-md bg-white/90 text-zinc-500 opacity-0 shadow-sm ring-1 ring-zinc-950/10 transition duration-150 ease-out group-hover/img:opacity-100 dark:bg-zinc-950/90 dark:text-zinc-400 dark:ring-white/10" aria-hidden="true">
                                <flux:icon icon="arrows-pointing-out" variant="micro" class="size-3" />
                            </span>

                            <div wire:sort:ignore class="absolute inset-x-0 bottom-0 flex items-center justify-end gap-1 bg-gradient-to-t from-black/55 via-black/25 to-transparent p-1.5 opacity-0 transition duration-150 ease-out group-hover/img:opacity-100 group-focus-within/img:opacity-100">
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
