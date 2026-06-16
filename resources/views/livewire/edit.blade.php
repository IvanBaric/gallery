@php
    $mediaCount = $this->galleryMediaCount();
    $seoCompleteCount = $gallery->seoCompleteMediaCount();
    $lastRegeneratedAt = $gallery->lastRegeneratedAt();
    $queuedAt = $gallery->regenerationQueuedAt();
    $canUpdate = $this->allowsGalleryAction('update');
    $canRegenerate = $this->allowsGalleryAction('regenerate');
    $canDelete = $this->allowsGalleryAction('delete');
    $deleteRequiresPassword = $this->deleteRequiresPassword();
@endphp

<x-admin-ui::page>
    <x-admin-ui::page-header :title="$gallery->displayTitle()" :description="__('Uređivanje galerije i fotografija.')">
        <x-slot:actions>
            <flux:button :href="route('admin.galleries.index')" wire:navigate variant="ghost" icon="arrow-left">
                {{ __('Natrag na galerije') }}
            </flux:button>
            @if ($canUpdate || $canRegenerate || $canDelete)
            <flux:dropdown position="bottom" align="end">
                <flux:tooltip :content="__('Postavke galerije')">
                    <flux:button type="button" variant="ghost" icon="cog-6-tooth" aria-label="{{ __('Postavke galerije') }}" />
                </flux:tooltip>

                <flux:menu>
                    @if ($canRegenerate)
                    <flux:menu.item icon="arrow-path" wire:click="openRegenerateConfirmation" wire:loading.attr="disabled">
                        {{ __('Regeneriraj galeriju') }}
                    </flux:menu.item>
                    @endif
                    @if ($canUpdate)
                    <flux:menu.item icon="pencil-square" wire:click="openMetaModal" wire:loading.attr="disabled">
                        {{ __('Podaci galerije') }}
                    </flux:menu.item>
                    @endif
                    @if ($canDelete)
                    <flux:menu.separator />
                    <flux:menu.item icon="trash" variant="danger" wire:click="openDeleteConfirmation" wire:loading.attr="disabled">
                        {{ __('Izbriši galeriju') }}
                    </flux:menu.item>
                    @endif
                </flux:menu>
            </flux:dropdown>
            @endif
        </x-slot:actions>
    </x-admin-ui::page-header>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <section class="rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-zinc-950/5 dark:bg-zinc-950 dark:ring-white/10">
            <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400 dark:text-zinc-500">{{ __('Slike') }}</p>
            <p class="mt-1 text-lg font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $mediaCount }}</p>
        </section>
        <section class="rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-zinc-950/5 dark:bg-zinc-950 dark:ring-white/10">
            <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400 dark:text-zinc-500">{{ __('Istaknuta') }}</p>
            <p class="mt-1 text-sm font-semibold text-zinc-950 dark:text-white">{{ $gallery->featured_media_id ? __('Da') : __('Ne') }}</p>
        </section>
        <section class="rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-zinc-950/5 dark:bg-zinc-950 dark:ring-white/10">
            <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400 dark:text-zinc-500">{{ __('SEO podaci') }}</p>
            <p class="mt-1 text-sm font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $seoCompleteCount }} / {{ $mediaCount }}</p>
        </section>
        <section class="rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-zinc-950/5 dark:bg-zinc-950 dark:ring-white/10">
            <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400 dark:text-zinc-500">{{ __('Tip') }}</p>
            <p class="mt-1 text-sm font-semibold text-zinc-950 dark:text-white">{{ $gallery->ownerTypeLabel() }}</p>
        </section>
        <section class="rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-zinc-950/5 dark:bg-zinc-950 dark:ring-white/10">
            <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400 dark:text-zinc-500">{{ __('Regenerirano') }}</p>
            @if ($queuedAt)
                <div class="mt-1">
                    <flux:badge size="sm" color="blue" icon="arrow-path">{{ __('U obradi') }}</flux:badge>
                </div>
            @elseif ($lastRegeneratedAt)
                <p class="mt-1 text-sm font-semibold text-zinc-950 dark:text-white">{{ $lastRegeneratedAt->diffForHumans() }}</p>
            @else
                <div class="mt-1">
                    <x-admin-ui::empty-value />
                </div>
            @endif
        </section>
    </div>

    <x-gallery::manager
        :model="$gallery"
        :collection="$gallery->collection_name"
        context="default"
        :title="__('Fotografije')"
        :description="__('Dodajte slike, uredite SEO podatke, promijenite redoslijed i istaknutu sliku.')"
        :migrate-legacy="false"
    />

    <flux:modal name="gallery-regenerate-confirm" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Regenerirati galeriju?') }}</flux:heading>
                <flux:subheading class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Galerija će se poslati u pozadinsku obradu za ponovno generiranje svih definiranih veličina slika.') }}
                </flux:subheading>
            </div>

            <div class="rounded-xl bg-zinc-50/70 px-4 py-3 text-sm text-zinc-600 ring-1 ring-zinc-950/5 dark:bg-zinc-900/80 dark:text-zinc-300 dark:ring-white/10">
                {{ $gallery->displayTitle() }}
            </div>

            <div class="flex items-center justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Odustani') }}</flux:button>
                </flux:modal.close>
                <flux:button type="button" variant="primary" icon="arrow-path" wire:click="regenerateGallery" wire:loading.attr="disabled" wire:target="regenerateGallery">
                    {{ __('Regeneriraj galeriju') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="gallery-meta-edit" class="max-w-2xl">
        <form wire:submit="saveMeta" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Podaci galerije') }}</flux:heading>
                <flux:subheading class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Naziv i opis koji pomažu organizaciji galerija.') }}</flux:subheading>
            </div>

            <div class="grid gap-4">
                <flux:input wire:model="form.title" :label="__('Naziv')" />
                <flux:textarea wire:model="form.description" :label="__('Opis')" rows="4" />
            </div>

            <div class="flex items-center justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Odustani') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check">{{ __('Spremi') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="gallery-delete-confirm" class="max-w-lg">
        <form wire:submit="deleteGallery" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Izbrisati galeriju?') }}</flux:heading>
                <flux:subheading class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    @if ($deleteRequiresPassword)
                        {{ __('Ova radnja trajno briše galeriju i sve pripadajuće fotografije. Za potvrdu unesite svoju lozinku.') }}
                    @else
                        {{ __('Ova radnja trajno briše galeriju i sve pripadajuće fotografije. Potvrdite brisanje za nastavak.') }}
                    @endif
                </flux:subheading>
            </div>

            @if ($deleteRequiresPassword)
                <flux:input wire:model="deletePassword" type="password" :label="__('Lozinka')" autocomplete="current-password" />
            @endif

            <div class="flex items-center justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Odustani') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger" icon="trash">{{ __('Trajno izbriši galeriju') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</x-admin-ui::page>
