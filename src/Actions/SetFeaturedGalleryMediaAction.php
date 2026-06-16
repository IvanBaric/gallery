<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Actions;

use Illuminate\Support\Facades\DB;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Gallery\Events\GalleryMediaFeatured;
use IvanBaric\Gallery\Models\Gallery;
use IvanBaric\Gallery\Support\GalleryPermissions;

final class SetFeaturedGalleryMediaAction
{
    public function handle(Gallery $gallery, int $mediaId): ActionResult
    {
        GalleryPermissions::authorize('update');

        if (! $gallery->getMedia($gallery->collection_name)->contains('id', $mediaId)) {
            return ActionResult::error(__('Fotografija nije pronađena u galeriji.'), 'gallery_media_not_found');
        }

        DB::transaction(static function () use ($gallery, $mediaId): void {
            $gallery->forceFill(['featured_media_id' => $mediaId])->save();
        });

        event(new GalleryMediaFeatured($gallery->refresh(), $mediaId));

        return ActionResult::success(__('Istaknuta fotografija je postavljena.'), $gallery);
    }
}
