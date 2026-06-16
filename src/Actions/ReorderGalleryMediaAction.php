<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Actions;

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

        Media::setNewOrder($ids);
        $gallery->touch();

        event(new GalleryMediaReordered($gallery->refresh(), $ids));

        return ActionResult::success(__('Redoslijed fotografija je ažuriran.'), ['media_ids' => $ids]);
    }
}
