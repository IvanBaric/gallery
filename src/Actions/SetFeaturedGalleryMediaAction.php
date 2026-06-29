<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Actions;

use Illuminate\Support\Facades\DB;
use IvanBaric\Corexis\Concerns\UsesOptimisticLocking;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Gallery\Events\GalleryMediaFeatured;
use IvanBaric\Gallery\Models\Gallery;
use IvanBaric\Gallery\Support\GalleryPermissions;

final class SetFeaturedGalleryMediaAction
{
    use UsesOptimisticLocking;

    public function handle(Gallery $gallery, int $mediaId, ?int $expectedLockVersion = null): ActionResult
    {
        GalleryPermissions::authorize('update');

        if (! $gallery->getMedia($gallery->collection_name)->contains('id', $mediaId)) {
            return ActionResult::error(__('Fotografija nije pronađena u galeriji.'), 'gallery_media_not_found');
        }

        $saved = DB::transaction(function () use ($gallery, $mediaId, $expectedLockVersion): bool {
            return $this->saveWithOptimisticLock($gallery, ['featured_media_id' => $mediaId], $expectedLockVersion);
        });

        if (! $saved) {
            return $this->staleModelResult();
        }

        event(new GalleryMediaFeatured($gallery->refresh(), $mediaId));

        return ActionResult::success(__('Istaknuta fotografija je postavljena.'), $gallery);
    }
}
