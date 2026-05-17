<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use IvanBaric\Gallery\Models\Gallery;

class RegenerateGalleryConversions implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int>|null  $mediaIds
     */
    public function __construct(
        public int $galleryId,
        public ?array $mediaIds = null,
    ) {}

    public function handle(): void
    {
        $gallery = Gallery::query()->find($this->galleryId);

        if (! $gallery) {
            return;
        }

        $ids = $this->mediaIds ?: $gallery
            ->getMedia($gallery->collection_name)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($ids === []) {
            return;
        }

        Artisan::call('media-library:regenerate', [
            'modelType' => Gallery::class,
            '--ids' => array_map(static fn (int $id): string => (string) $id, $ids),
            '--with-responsive-images' => (bool) config('gallery.conversions.generate_responsive_images', false),
        ]);

        $gallery->markRegenerated(count($ids));
    }
}
