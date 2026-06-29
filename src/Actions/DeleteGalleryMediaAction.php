<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Actions;

use Illuminate\Support\Facades\DB;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Gallery\Events\GalleryMediaDeleted;
use IvanBaric\Gallery\Models\Gallery;
use IvanBaric\Gallery\Support\GalleryPermissions;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

final class DeleteGalleryMediaAction
{
    /**
     * @param  array<int, SpatieMedia|int>  $media
     */
    public function handle(Gallery $gallery, array $media): ActionResult
    {
        GalleryPermissions::authorize('delete');

        $mediaItems = collect($media)
            ->map(fn (SpatieMedia|int $item): ?SpatieMedia => $item instanceof SpatieMedia
                ? $item
                : $gallery->getMedia($gallery->collection_name)->firstWhere('id', $item))
            ->filter(fn (SpatieMedia $item): bool => (string) $item->model_type === (string) $gallery->getMorphClass()
                && (int) $item->model_id === (int) $gallery->getKey())
            ->values();

        if ($mediaItems->isEmpty()) {
            return ActionResult::error(__('Odaberite barem jednu fotografiju.'), 'gallery_media_empty');
        }

        $mediaIds = $mediaItems->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $featuredDeleted = in_array((int) $gallery->featured_media_id, $mediaIds, true);

        DB::transaction(static function () use ($gallery, $mediaItems, $featuredDeleted): void {
            /** @var Gallery $lockedGallery */
            $lockedGallery = Gallery::query()
                ->whereKey($gallery->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            foreach ($mediaItems as $item) {
                $item->delete();
            }

            if ($featuredDeleted) {
                $lockedGallery->forceFill(['featured_media_id' => null])->save();
            } else {
                $lockedGallery->touch();
            }
        });

        event(new GalleryMediaDeleted($gallery->refresh(), $mediaIds));

        return ActionResult::success(
            message: __('Fotografije su obrisane.'),
            data: ['media_ids' => $mediaIds],
        );
    }
}
