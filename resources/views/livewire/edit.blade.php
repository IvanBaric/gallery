<x-admin-ui::page>
    <x-admin-ui::page-header :title="$gallery->displayTitle()" :description="__('Uređivanje galerije i fotografija.')">
        <x-slot:actions>
            <flux:button :href="route('admin.galleries.index')" wire:navigate variant="ghost" icon="arrow-left">
                {{ __('Natrag na galerije') }}
            </flux:button>
        </x-slot:actions>
    </x-admin-ui::page-header>

    <x-admin-ui::panel>
        <x-admin-ui::panel-header :title="__('Podaci galerije')" :description="__('Naziv i opis koji pomažu organizaciji galerija.')" />

        <form wire:submit="saveMeta" class="grid gap-4 p-6 sm:grid-cols-2 sm:p-7">
            <flux:input wire:model="title" :label="__('Naziv')" />
            <div class="sm:col-span-2">
                <flux:textarea wire:model="description" :label="__('Opis')" rows="3" />
            </div>

            <div class="sm:col-span-2 flex justify-end">
                <flux:button type="submit" variant="primary" icon="check">
                    {{ __('Spremi') }}
                </flux:button>
            </div>
        </form>
    </x-admin-ui::panel>

    <x-gallery::manager
        :model="$gallery"
        :collection="$gallery->collection_name"
        context="default"
        :title="__('Fotografije')"
        :description="__('Dodajte slike, uredite SEO podatke, promijenite redoslijed i istaknutu sliku.')"
        :migrate-legacy="false"
    />
</x-admin-ui::page>

