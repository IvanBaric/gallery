<x-admin-ui::page>
    <x-admin-ui::page-header :title="$gallery->displayTitle()" :description="__('Uređivanje galerije i fotografija.')">
        <x-slot:actions>
            <flux:button type="button" variant="ghost" icon="pencil-square" x-on:click="$flux.modal('gallery-meta-edit').show()">
                {{ __('Podaci galerije') }}
            </flux:button>
            <flux:button type="button" variant="danger" icon="trash" x-on:click="$flux.modal('gallery-delete-confirm').show()">
                {{ __('Izbriši galeriju') }}
            </flux:button>
            <flux:button :href="route('admin.galleries.index')" wire:navigate variant="ghost" icon="arrow-left">
                {{ __('Natrag na galerije') }}
            </flux:button>
        </x-slot:actions>
    </x-admin-ui::page-header>

    <x-gallery::manager
        :model="$gallery"
        :collection="$gallery->collection_name"
        context="default"
        :title="__('Fotografije')"
        :description="__('Dodajte slike, uredite SEO podatke, promijenite redoslijed i istaknutu sliku.')"
        :migrate-legacy="false"
    />

    <flux:modal name="gallery-meta-edit" class="max-w-2xl">
        <form wire:submit="saveMeta" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Podaci galerije') }}</flux:heading>
                <flux:subheading class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Naziv i opis koji pomažu organizaciji galerija.') }}</flux:subheading>
            </div>

            <div class="grid gap-4">
                <flux:input wire:model="title" :label="__('Naziv')" />
                <flux:textarea wire:model="description" :label="__('Opis')" rows="4" />
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
                    {{ __('Ova radnja trajno briše galeriju i sve pripadajuće fotografije. Za potvrdu unesite svoju lozinku.') }}
                </flux:subheading>
            </div>

            <div class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200 dark:bg-red-950/30 dark:text-red-300 dark:ring-red-900/50">
                {{ __('Nakon brisanja nije moguće vratiti podatke.') }}
            </div>

            <flux:input wire:model="deletePassword" type="password" :label="__('Lozinka')" autocomplete="current-password" />

            <div class="flex items-center justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Odustani') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger" icon="trash">{{ __('Trajno izbriši galeriju') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</x-admin-ui::page>
