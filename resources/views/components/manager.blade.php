@props([
    'model',
    'collection' => config('gallery.default_collection', 'images'),
    'context' => 'default',
    'title' => null,
    'description' => null,
    'migrateLegacy' => true,
])

@php
    $key = 'gallery-manager-'.md5($model::class.'|'.$model->getKey().'|'.$collection);
@endphp

<livewire:gallery.manager
    :model="$model"
    :collection="$collection"
    :context="$context"
    :title="$title"
    :description="$description"
    :migrate-legacy="$migrateLegacy"
    :key="$key"
/>
