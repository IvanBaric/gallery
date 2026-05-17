@props([
    'model',
    'collection' => config('gallery.default_collection', 'images'),
    'emptyOnly' => true,
    'allowReplace' => false,
    'showCurrent' => true,
    'label' => null,
    'placeholder' => null,
    'buttonLabel' => null,
    'description' => null,
])

@php
    $key = 'gallery-standalone-selector-'.md5($model::class.'|'.$model->getKey().'|'.$collection);
@endphp

<livewire:gallery.standalone-selector
    :model="$model"
    :collection="$collection"
    :empty-only="$emptyOnly"
    :allow-replace="$allowReplace"
    :show-current="$showCurrent"
    :label="$label"
    :placeholder="$placeholder"
    :button-label="$buttonLabel"
    :description="$description"
    :key="$key"
/>
