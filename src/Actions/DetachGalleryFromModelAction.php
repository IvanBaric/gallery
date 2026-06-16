<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Gallery\Events\GalleryDetachedFromModel;
use IvanBaric\Gallery\Models\Gallery;
use IvanBaric\Gallery\Support\GalleryPermissions;

final class DetachGalleryFromModelAction
{
    public function handle(Model $model, Gallery $gallery, string $collection = 'images'): ActionResult
    {
        GalleryPermissions::authorize('attach');

        $modelType = $model->getMorphClass();
        $modelKey = $model->getKey();

        if ((string) $gallery->galleryable_type !== (string) $modelType || (string) $gallery->galleryable_id !== (string) $modelKey) {
            return ActionResult::error(
                message: __('Galerija nije povezana s ovim zapisom.'),
                errors: ['gallery' => [__('Galerija nije povezana s ovim zapisom.')]],
                code: 'gallery_not_attached',
            );
        }

        DB::transaction(static function () use ($gallery): void {
            $gallery->forceFill([
                'galleryable_type' => null,
                'galleryable_id' => null,
            ])->save();
        });

        GalleryDetachedFromModel::dispatch($gallery, $model::class, $modelKey, $collection);

        return ActionResult::success(
            message: __('Galerija je uklonjena.'),
            data: $gallery,
            code: 'gallery_detached',
        );
    }
}
