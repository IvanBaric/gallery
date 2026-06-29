<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Actions;

use Illuminate\Support\Facades\DB;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Gallery\Events\GalleryDeleted;
use IvanBaric\Gallery\Models\Gallery;
use IvanBaric\Gallery\Support\GalleryPermissions;

final class DeleteGalleryAction
{
    public function handle(Gallery $gallery): ActionResult
    {
        GalleryPermissions::authorize('delete');

        $galleryId = $gallery->getKey();
        $galleryId = is_int($galleryId) || is_string($galleryId) ? $galleryId : (string) $galleryId;
        $uuid = is_string($gallery->uuid) ? $gallery->uuid : null;
        $collection = (string) $gallery->collection_name;

        DB::transaction(static function () use ($gallery, $collection): void {
            /** @var Gallery $lockedGallery */
            $lockedGallery = Gallery::query()
                ->whereKey($gallery->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedGallery->clearMediaCollection($collection);
            $lockedGallery->delete();
        });

        event(new GalleryDeleted($galleryId, $uuid, $collection));

        return ActionResult::success(__('Galerija je obrisana.'));
    }
}
