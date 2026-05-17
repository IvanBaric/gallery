@props([
    'media' => null,
    'images' => null,
    'title' => null,
    'featured' => false,
    'featuredLabel' => __('Izdvojeno'),
    'fallbackAlt' => null,
    'mainConversion' => 'large',
    'lightboxConversion' => 'large',
    'thumbnailConversion' => 'thumb',
    'showFeaturedBadge' => true,
    'showZoomHint' => true,
    'showCounter' => true,
    'showThumbnails' => true,
    'showLightboxTitle' => true,
    'showEmptyState' => true,
    'emptyTitle' => __('Fotografije uskoro'),
    'emptyDescription' => __('Galerija trenutno nema fotografija.'),
    'openLabel' => __('Otvori galeriju u punom prikazu'),
    'closeLabel' => __('Zatvori galeriju'),
    'previousLabel' => __('Prethodna fotografija'),
    'nextLabel' => __('Sljedeća fotografija'),
    'thumbnailLabel' => __('Prikaži fotografiju'),
    'scrollPreviousLabel' => __('Pomakni sličice ulijevo'),
    'scrollNextLabel' => __('Pomakni sličice udesno'),
    'dialogLabel' => __('Galerija fotografija'),
    'zoomLabel' => __('Uvećaj'),
    'aspect' => 'aspect-[4/3]',
    'mainImageClass' => 'object-contain',
    'thumbnailImageClass' => 'object-cover',
])

@php
    $source = $images ?? $media ?? [];

    $mediaUrl = static function (mixed $item, array $preferred): ?string {
        if (! is_object($item) || ! method_exists($item, 'getUrl')) {
            return null;
        }

        foreach (array_filter($preferred) as $conversion) {
            try {
                if (method_exists($item, 'getAvailableUrl')) {
                    $url = $item->getAvailableUrl([(string) $conversion]);

                    if (filled($url)) {
                        return $url;
                    }
                }

                if (method_exists($item, 'hasGeneratedConversion') && ! $item->hasGeneratedConversion((string) $conversion)) {
                    continue;
                }

                $url = $item->getUrl((string) $conversion);

                if (filled($url)) {
                    return $url;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return $item->getUrl();
        } catch (\Throwable) {
            return null;
        }
    };

    $galleryImages = collect($source)
        ->map(function (mixed $item, int $index) use ($fallbackAlt, $mainConversion, $lightboxConversion, $thumbnailConversion, $mediaUrl): ?array {
            if (is_array($item)) {
                $main = data_get($item, 'main') ?: data_get($item, 'large') ?: data_get($item, 'url') ?: data_get($item, 'src');
                $lightbox = data_get($item, 'lightbox') ?: data_get($item, 'full') ?: data_get($item, 'large') ?: $main;
                $thumb = data_get($item, 'thumb') ?: data_get($item, 'thumbnail') ?: data_get($item, 'preview') ?: $main;
                $alt = (string) (data_get($item, 'alt') ?: $fallbackAlt ?: __('Fotografija :number', ['number' => $index + 1]));

                return $main ? [
                    'thumb' => $thumb ?: $main,
                    'main' => $main,
                    'lightbox' => $lightbox ?: $main,
                    'alt' => $alt,
                ] : null;
            }

            $main = $mediaUrl($item, [$mainConversion, 'large', 'medium_large']);
            $lightbox = $mediaUrl($item, [$lightboxConversion, $mainConversion, 'xlarge', 'large']);
            $thumb = $mediaUrl($item, [$thumbnailConversion, 'thumb', 'thumbnail']);

            if (! $main) {
                return null;
            }

            $altFallback = $fallbackAlt
                ? $fallbackAlt.' '.__('fotografija').' '.($index + 1)
                : __('Fotografija :number', ['number' => $index + 1]);

            return [
                'thumb' => $thumb ?: $main,
                'main' => $main,
                'lightbox' => $lightbox ?: $main,
                'alt' => method_exists($item, 'altText') ? $item->altText($altFallback) : (string) ($item->name ?? $altFallback),
            ];
        })
        ->filter()
        ->values();

    $imageCount = $galleryImages->count();
    $hasImages = $imageCount > 0;
    $hasMultiple = $imageCount > 1;
@endphp

<section
    {{ $attributes->class(['gallery-lightbox']) }}
    x-data="{
        active: 0,
        open: false,
        loaded: true,
        touchStartX: 0,
        touchStartY: 0,
        thumbCanPrev: false,
        thumbCanNext: false,
        images: @js($galleryImages->all()),
        get current() { return this.images[this.active] ?? null; },
        select(i) {
            if (i === this.active) return;
            this.loaded = false;
            this.active = i;
        },
        openAt(i) {
            if (! this.images.length) return;
            this.active = i;
            this.open = true;
            document.documentElement.style.overflow = 'hidden';
        },
        close() {
            this.open = false;
            document.documentElement.style.overflow = '';
        },
        next() {
            if (this.images.length < 2) return;
            this.loaded = false;
            this.active = (this.active + 1) % this.images.length;
        },
        prev() {
            if (this.images.length < 2) return;
            this.loaded = false;
            this.active = (this.active - 1 + this.images.length) % this.images.length;
        },
        onSwipeStart(e) {
            this.touchStartX = e.changedTouches[0].screenX;
            this.touchStartY = e.changedTouches[0].screenY;
        },
        onSwipeEnd(e) {
            const dx = e.changedTouches[0].screenX - this.touchStartX;
            const dy = e.changedTouches[0].screenY - this.touchStartY;
            if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) {
                dx < 0 ? this.next() : this.prev();
            }
        },
        scrollActiveIntoView() {
            this.$nextTick(() => {
                const strip = this.$refs.lightboxStrip;
                if (! strip) return;
                const target = strip.querySelector('[data-active=&quot;true&quot;]');
                target?.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            });
        },
        scrollActiveThumbIntoView() {
            this.$nextTick(() => {
                const strip = this.$refs.thumbStrip;
                if (! strip) return;
                const target = strip.querySelector('[data-main-active=&quot;true&quot;]');
                target?.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                this.updateThumbControls();
            });
        },
        updateThumbControls() {
            const strip = this.$refs.thumbStrip;
            if (! strip) return;
            const maxScroll = Math.max(0, strip.scrollWidth - strip.clientWidth - 1);
            this.thumbCanPrev = strip.scrollLeft > 1;
            this.thumbCanNext = strip.scrollLeft < maxScroll;
        },
        scrollThumbs(direction = 1) {
            const strip = this.$refs.thumbStrip;
            if (! strip) return;
            const step = Math.max(220, Math.floor(strip.clientWidth * 0.8));
            strip.scrollBy({ left: direction * step, behavior: 'smooth' });
            window.setTimeout(() => this.updateThumbControls(), 220);
        }
    }"
    x-init="
        $watch('active', () => {
            scrollActiveIntoView();
            scrollActiveThumbIntoView();
        });
        $nextTick(() => {
            updateThumbControls();
            scrollActiveThumbIntoView();
        });
    "
    @keydown.escape.window="open && close()"
    @keydown.arrow-right.window="open && next()"
    @keydown.arrow-left.window="open && prev()"
    @resize.window.debounce.150ms="updateThumbControls()"
>
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-zinc-950/5 dark:bg-zinc-950 dark:ring-white/10">
        @if ($hasImages)
            <button
                type="button"
                @click="openAt(active)"
                @touchstart.passive="onSwipeStart($event)"
                @touchend.passive="onSwipeEnd($event)"
                @class([
                    'group relative block w-full cursor-zoom-in overflow-hidden bg-zinc-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:bg-zinc-950 dark:focus-visible:ring-zinc-500 dark:focus-visible:ring-offset-zinc-950',
                    $aspect,
                ])
                aria-label="{{ $openLabel }}"
            >
                <template x-if="current">
                    <img
                        :src="current.main"
                        :alt="current.alt"
                        @load="loaded = true"
                        x-on:error="loaded = true"
                        class="absolute inset-0 h-full w-full transition-opacity duration-300 {{ $mainImageClass }}"
                        :class="loaded ? 'opacity-100' : 'opacity-0'"
                        fetchpriority="high"
                        draggable="false"
                    />
                </template>

                <div class="pointer-events-none absolute inset-x-0 top-0 flex items-start justify-between gap-3 p-4">
                    @if ($featured && $showFeaturedBadge)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/90 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-amber-600 shadow-sm ring-1 ring-zinc-950/5 backdrop-blur dark:bg-zinc-950/85 dark:text-amber-400 dark:ring-white/10">
                            <flux:icon.star class="size-3" />
                            {{ $featuredLabel }}
                        </span>
                    @else
                        <span></span>
                    @endif

                    @if ($showZoomHint)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-950/70 px-3 py-1 text-[11px] font-medium tracking-wide text-white opacity-0 shadow-lg ring-1 ring-white/10 backdrop-blur transition duration-150 ease-out group-hover:opacity-100 group-focus-visible:opacity-100">
                            <flux:icon.arrows-pointing-out class="size-3.5" />
                            {{ $zoomLabel }}
                        </span>
                    @endif
                </div>

                @if ($hasMultiple && $showCounter)
                    <div class="pointer-events-none absolute bottom-4 right-4 inline-flex items-center rounded-full bg-zinc-950/75 px-3 py-1 text-[12px] font-medium text-white shadow-lg ring-1 ring-white/10 backdrop-blur">
                        <span class="tabular-nums" x-text="active + 1"></span>
                        <span class="mx-1.5 text-white/40">/</span>
                        <span class="tabular-nums text-white/80">{{ $imageCount }}</span>
                    </div>
                @endif
            </button>
        @elseif ($showEmptyState)
            <div class="flex {{ $aspect }} flex-col items-center justify-center gap-4 bg-zinc-50/80 px-6 text-center dark:bg-zinc-950">
                <span class="flex size-16 items-center justify-center rounded-2xl bg-white text-zinc-300 ring-1 ring-zinc-950/5 dark:bg-zinc-900 dark:text-zinc-600 dark:ring-white/10">
                    <flux:icon.photo class="size-8" />
                </span>
                <div>
                    <p class="font-semibold text-zinc-950 dark:text-white">{{ $emptyTitle }}</p>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $emptyDescription }}</p>
                </div>
            </div>
        @endif

        @if ($hasMultiple && $showThumbnails)
            <div class="border-t border-zinc-100/70 px-3 py-3 dark:border-zinc-800/70">
                <div class="relative mx-auto w-full max-w-[38rem] px-10">
                    <button
                        type="button"
                        x-cloak
                        x-show="thumbCanPrev"
                        @click="scrollThumbs(-1)"
                        class="absolute left-0 top-1/2 z-10 inline-flex size-8 -translate-y-1/2 items-center justify-center rounded-full bg-zinc-950/80 text-white shadow-lg ring-1 ring-white/15 transition hover:bg-zinc-950 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white"
                        aria-label="{{ $scrollPreviousLabel }}"
                    >
                        <flux:icon.chevron-left class="size-4" />
                    </button>
                    <button
                        type="button"
                        x-cloak
                        x-show="thumbCanNext"
                        @click="scrollThumbs(1)"
                        class="absolute right-0 top-1/2 z-10 inline-flex size-8 -translate-y-1/2 items-center justify-center rounded-full bg-zinc-950/80 text-white shadow-lg ring-1 ring-white/15 transition hover:bg-zinc-950 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white"
                        aria-label="{{ $scrollNextLabel }}"
                    >
                        <flux:icon.chevron-right class="size-4" />
                    </button>
                    <div
                        x-ref="thumbStrip"
                        @scroll.passive="updateThumbControls()"
                        class="flex w-full max-w-full snap-x gap-2 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                    >
                        @foreach ($galleryImages as $i => $thumb)
                            <button
                                type="button"
                                @click="select({{ $i }})"
                                @dblclick="openAt({{ $i }})"
                                :data-main-active="active === {{ $i }}"
                                :class="active === {{ $i }} ? 'ring-2 ring-zinc-900 dark:ring-white' : 'ring-1 ring-zinc-950/5 hover:ring-zinc-950/15 dark:ring-white/10 dark:hover:ring-white/20'"
                                class="relative h-16 w-24 shrink-0 snap-start overflow-hidden rounded-lg bg-zinc-100 transition duration-150 ease-out focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400 dark:bg-zinc-900"
                                aria-label="{{ $thumbnailLabel }} {{ $i + 1 }}"
                            >
                                <img src="{{ $thumb['thumb'] }}" alt="{{ $thumb['alt'] }}" class="h-full w-full {{ $thumbnailImageClass }}" loading="lazy" draggable="false" />
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition duration-200 ease-out"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition duration-150 ease-in"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click.self="close()"
        class="fixed inset-0 z-[60] flex h-[100dvh] max-h-[100dvh] flex-col overflow-hidden bg-zinc-950/92 backdrop-blur-md"
        role="dialog"
        aria-modal="true"
        aria-label="{{ $dialogLabel }}"
        style="display: none;"
    >
        <header class="shrink-0 flex items-center justify-between gap-4 px-5 pt-4 sm:px-8 sm:pt-6">
            @if ($showCounter)
                <p class="text-[13px] font-medium text-white/80">
                    <span class="tabular-nums text-white" x-text="active + 1"></span>
                    <span class="mx-1.5 text-white/30">/</span>
                    <span class="tabular-nums text-white/60" x-text="images.length"></span>
                </p>
            @else
                <span></span>
            @endif
            @if ($showLightboxTitle && filled($title))
                <p class="hidden truncate text-[13px] text-white/60 sm:block">{{ $title }}</p>
            @endif
            <button
                type="button"
                @click="close()"
                class="inline-flex size-10 items-center justify-center rounded-full bg-white/5 text-white ring-1 ring-white/10 transition duration-150 ease-out hover:bg-white/15 hover:ring-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/40"
                aria-label="{{ $closeLabel }}"
            >
                <flux:icon.x-mark class="size-5" />
            </button>
        </header>

        <div
            class="relative flex min-h-0 flex-1 items-center justify-center px-4 py-3 sm:px-16 sm:py-5"
            @touchstart.passive="onSwipeStart($event)"
            @touchend.passive="onSwipeEnd($event)"
        >
            <template x-if="images.length > 1">
                <button
                    type="button"
                    @click.stop="prev()"
                    class="absolute left-3 top-1/2 z-10 inline-flex size-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/5 text-white ring-1 ring-white/10 transition duration-150 ease-out hover:bg-white/15 hover:ring-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/40 sm:left-6"
                    aria-label="{{ $previousLabel }}"
                >
                    <flux:icon.chevron-left class="size-6" />
                </button>
            </template>

            <template x-if="current">
                <div class="flex h-full max-h-full min-h-0 max-w-full items-center justify-center" :key="active">
                    <img
                        :src="current.lightbox"
                        :alt="current.alt"
                        @load="loaded = true"
                        x-on:error="loaded = true"
                        class="max-h-full max-w-full select-none object-contain transition duration-200 ease-out"
                        :class="loaded ? 'opacity-100 scale-100' : 'opacity-0 scale-[0.98]'"
                        draggable="false"
                    />
                </div>
            </template>

            <template x-if="images.length > 1">
                <button
                    type="button"
                    @click.stop="next()"
                    class="absolute right-3 top-1/2 z-10 inline-flex size-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/5 text-white ring-1 ring-white/10 transition duration-150 ease-out hover:bg-white/15 hover:ring-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/40 sm:right-6"
                    aria-label="{{ $nextLabel }}"
                >
                    <flux:icon.chevron-right class="size-6" />
                </button>
            </template>
        </div>

        @if ($showThumbnails)
            <template x-if="images.length > 1">
                <div class="shrink-0 px-4 pb-4 sm:px-8 sm:pb-5">
                    <div x-ref="lightboxStrip" class="mx-auto flex max-w-full gap-2 overflow-x-auto px-1 py-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        <template x-for="(img, i) in images" :key="i">
                            <button
                                type="button"
                                @click.stop="select(i)"
                                :data-active="active === i"
                                :class="active === i ? 'ring-2 ring-white opacity-100' : 'ring-1 ring-white/15 opacity-55 hover:opacity-90'"
                                class="relative h-16 w-24 shrink-0 overflow-hidden rounded-lg bg-zinc-900 transition duration-150 ease-out focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                                :aria-label="'{{ $thumbnailLabel }} ' + (i + 1)"
                                :aria-current="active === i ? 'true' : 'false'"
                            >
                                <img :src="img.thumb" :alt="img.alt" class="h-full w-full {{ $thumbnailImageClass }}" loading="lazy" draggable="false" />
                            </button>
                        </template>
                    </div>
                </div>
            </template>
        @endif
    </div>
</section>
