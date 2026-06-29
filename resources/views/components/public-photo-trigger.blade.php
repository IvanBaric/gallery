@props([
    'src',
    'alt' => '',
    'imageClass' => 'aspect-[4/3] w-full object-cover',
    'frameClass' => '',
    'caption' => null,
    'captionMode' => 'below',
    'indicatorSize' => 'md',
])

@php
    $caption = trim((string) $caption);
    $captionMode = in_array($captionMode, ['below', 'overlay', 'none'], true) ? $captionMode : 'below';
    $indicatorSize = in_array($indicatorSize, ['sm', 'md'], true) ? $indicatorSize : 'md';
    $indicatorBoxClass = $indicatorSize === 'sm'
        ? 'size-8'
        : 'size-12';
    $indicatorIconClass = $indicatorSize === 'sm'
        ? 'size-4'
        : 'size-6';
@endphp

<button
    {{ $attributes
        ->merge([
            'type' => 'button',
            'title' => $alt,
            'aria-label' => $alt,
        ])
        ->class('group relative block cursor-pointer overflow-hidden rounded-lg bg-zinc-100 text-left shadow-sm shadow-zinc-950/5 transition duration-200 hover:-translate-y-0.5 hover:shadow-md hover:shadow-zinc-950/10 focus:outline-none focus:ring-2 focus:ring-[color:var(--niva-primary)] focus:ring-offset-2 focus:ring-offset-white dark:bg-zinc-900 dark:shadow-black/20 dark:focus:ring-offset-zinc-950') }}
>
    <span @class(['relative block overflow-hidden', $frameClass])>
        <img src="{{ $src }}" alt="{{ $alt }}" class="{{ $imageClass }} transition duration-500 group-hover:scale-[1.03]">
        <span class="pointer-events-none absolute inset-0 bg-gradient-to-t from-zinc-950/35 via-zinc-950/5 to-transparent opacity-0 transition duration-300 group-hover:opacity-100" aria-hidden="true"></span>
        <span @class([
            'pointer-events-none absolute left-1/2 top-1/2 inline-flex -translate-x-1/2 -translate-y-1/2 scale-95 items-center justify-center rounded-full bg-white/88 text-zinc-950 opacity-0 shadow-md shadow-zinc-950/15 backdrop-blur transition duration-300 group-hover:scale-100 group-hover:opacity-100',
            $indicatorBoxClass,
        ]) aria-hidden="true">
            <flux:icon name="magnifying-glass-plus" class="{{ $indicatorIconClass }}" />
        </span>

        @if ($captionMode === 'overlay' && $caption !== '')
            <span class="pointer-events-none absolute inset-x-0 bottom-0 bg-zinc-950/65 px-3 py-2 text-sm text-white">{{ $caption }}</span>
        @endif
    </span>

    @if ($captionMode === 'below' && $caption !== '')
        <span class="block px-3 py-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $caption }}</span>
    @endif
</button>
