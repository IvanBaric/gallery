<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Actions;

use Illuminate\Support\Facades\DB;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Gallery\Events\GalleryMediaReordered;
use IvanBaric\Gallery\Models\Gallery;
use IvanBaric\Gallery\Models\Media;
use IvanBaric\Gallery\Support\GalleryPermissions;

final class ReorderGalleryMediaAction
{
    public function handle(Gallery $gallery, int $mediaId, int $position): ActionResult
    {
        GalleryPermissions::authorize('update');

        $ids = $gallery->getMedia($gallery->collection_name)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->reject(static fn (int $id): bool => $id === $mediaId)
            ->values()
            ->all();

        if (! $gallery->getMedia($gallery->collection_name)->contains('id', $mediaId)) {
            return ActionResult::error(__('Fotografija nije pronađena u galeriji.'), 'gallery_media_not_found');
        }

        $position = max(0, min($position, count($ids)));
        array_splice($ids, $position, 0, [$mediaId]);

        DB::transaction(static function () use ($gallery, $ids): void {
            /** @var Gallery $lockedGallery */
            $lockedGallery = Gallery::query()
                ->whereKey($gallery->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Media::query()
                ->where('model_type', $lockedGallery->getMorphClass())
                ->where('model_id', $lockedGallery->getKey())
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            Media::setNewOrder($ids);
            $lockedGallery->touch();
        });

        event(new GalleryMediaReordered($gallery->refresh(), $ids));

        return ActionResult::success(__('Redoslijed fotografija je ažuriran.'), ['media_ids' => $ids]);
    }
}
