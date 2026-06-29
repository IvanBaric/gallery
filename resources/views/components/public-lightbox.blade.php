@props([
    'mediaItems',
    'title' => null,
    'dialogLabel' => __('Pregled fotografije'),
])

@php
    $items = collect($mediaItems)->values();
    $count = $items->count();
    $fallbackTitle = $title ?: __('Galerija');
@endphp

@if ($count > 0)
    <template x-teleport="body">
        <div
            x-cloak
            x-show="open"
            x-transition.opacity.duration.200ms
            class="fixed inset-0 z-[120] h-[100dvh] w-screen overflow-hidden bg-zinc-950/94 text-white"
            role="dialog"
            aria-modal="true"
            aria-label="{{ $dialogLabel }}"
        >
            <button x-ref="closeButton" type="button" x-on:click="close()" class="cursor-pointer absolute right-4 top-4 z-20 inline-flex size-11 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/80" aria-label="{{ __('Zatvori') }}">
                <svg class="size-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                </svg>
            </button>

            <div class="absolute left-4 top-4 z-20 rounded-full bg-white/10 px-3 py-1.5 text-sm font-semibold text-white backdrop-blur">
                <span x-text="index + 1"></span> / {{ $count }}
            </div>

            <button type="button" x-on:click="previous()" class="cursor-pointer absolute left-4 top-1/2 z-20 inline-flex size-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/80" aria-label="{{ __('Prethodna fotografija') }}">
                <svg class="size-7" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M11.78 4.22a.75.75 0 0 1 0 1.06L7.06 10l4.72 4.72a.75.75 0 1 1-1.06 1.06l-5.25-5.25a.75.75 0 0 1 0-1.06l5.25-5.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
                </svg>
            </button>

            <button type="button" x-on:click="next()" class="cursor-pointer absolute right-4 top-1/2 z-20 inline-flex size-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur transition hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/80" aria-label="{{ __('Sljedeća fotografija') }}">
                <svg class="size-7" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M8.22 4.22a.75.75 0 0 1 1.06 0l5.25 5.25a.75.75 0 0 1 0 1.06l-5.25 5.25a.75.75 0 0 1-1.06-1.06L12.94 10 8.22 5.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                </svg>
            </button>

            <div class="flex h-full max-h-[100dvh] flex-col px-5 pb-5 pt-16 sm:px-8">
                <div class="flex min-h-0 flex-1 items-center justify-center">
                    <img
                        x-bind:src="items[index]?.url"
                        x-bind:alt="items[index]?.alt"
                        x-bind:class="imageVisible ? 'opacity-100' : 'opacity-0'"
                        class="max-h-[calc(100vh-13rem)] max-w-full rounded-lg object-contain opacity-100 shadow-lg shadow-black/30 transition-opacity duration-150 ease-out"
                    >
                </div>

                <div class="mt-5 shrink-0">
                    <div class="mb-3 text-center text-sm text-zinc-300">
                        {{ trans_choice(':count fotografija u galeriji|:count fotografije u galeriji|:count fotografija u galeriji', $count, ['count' => $count]) }}
                    </div>
                    <flux:carousel
                        :arrows="$count > 4"
                        :fade="true"
                        snap="mandatory"
                        scroll="smooth"
                        advance="page"
                        aria-label="{{ __('Fotografije u galeriji') }}"
                        class="mx-auto max-w-5xl"
                        track:class="gap-3 px-1 py-1 pb-3"
                        arrows:position="outside"
                    >
                        @foreach ($items as $media)
                            @php
                                $alt = data_get($media, 'alt') ?: (method_exists($media, 'altText')
                                    ? $media->altText($fallbackTitle)
                                    : (string) ($media->name ?? __('Fotografija :number', ['number' => $loop->iteration])));
                                $thumb = data_get($media, 'thumb');

                                if (! $thumb && is_object($media) && method_exists($media, 'getAvailableUrl')) {
                                    $thumb = $media->getAvailableUrl(['thumb']);
                                }

                                if (! $thumb && is_object($media) && method_exists($media, 'getUrl')) {
                                    $thumb = $media->getUrl();
                                }
                            @endphp

                            <flux:carousel.slide class="basis-auto">
                                <button
                                    type="button"
                                    x-on:click="go({{ $loop->index }})"
                                    class="cursor-pointer overflow-hidden rounded-md outline outline-2 outline-offset-2 transition duration-200"
                                    x-bind:class="index === {{ $loop->index }} ? 'outline-[color:var(--niva-primary-300)] opacity-100' : 'outline-white/15 opacity-55 hover:opacity-90'"
                                    title="{{ $alt }}"
                                    aria-label="{{ __('Otvori fotografiju :number', ['number' => $loop->iteration]) }}"
                                >
                                    <img src="{{ $thumb ?: '' }}" alt="" class="h-16 w-24 object-cover sm:h-20 sm:w-28">
                                </button>
                            </flux:carousel.slide>
                        @endforeach
                    </flux:carousel>
                </div>
            </div>
        </div>
    </template>
@endif
