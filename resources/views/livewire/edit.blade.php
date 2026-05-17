<x-admin-ui::page>
    <x-admin-ui::page-header :title="$gallery->displayTitle()" :description="__('Uređivanje galerije i fotografija.')">
        <x-slot:actions>
            <flux:button type="button" variant="ghost" icon="pencil-square" x-on:click="$flux.modal('gallery-meta-edit').show()">
                {{ __('Podaci galerije') }}
            </flux:button>
            <flux:button :href="route('admin.galleries.index')" wire:navigate variant="ghost" icon="arrow-left">
                {{ __('Natrag na galerije') }}
            </flux:button>
        </x-slot:actions>
    </x-admin-ui::page-header>

    <x-admin-ui::panel>
        <x-admin-ui::panel-header :title="__('Podaci galerije')" :description="__('Naziv i opis koji pomažu organizaciji galerija.')">
            <x-slot:actions>
                <flux:button type="button" size="sm" variant="ghost" icon="pencil-square" x-on:click="$flux.modal('gallery-meta-edit').show()">
                    {{ __('Uredi') }}
                </flux:button>
            </x-slot:actions>
        </x-admin-ui::panel-header>

        <div class="grid gap-4 p-6 sm:grid-cols-2 sm:p-7">
            <div>
                <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400 dark:text-zinc-500">{{ __('Naziv') }}</p>
                <p class="mt-1.5 text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $title }}</p>
            </div>
            <div class="sm:col-span-2">
                <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-400 dark:text-zinc-500">{{ __('Opis') }}</p>
                <p class="mt-1.5 text-sm text-zinc-700 dark:text-zinc-300">{{ filled($description) ? $description : '—' }}</p>
            </div>
        </div>
    </x-admin-ui::panel>

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
</x-admin-ui::page>
