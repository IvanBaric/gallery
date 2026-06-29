<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Gallery\Events\GalleryAttachedToModel;
use IvanBaric\Gallery\Models\Gallery;
use IvanBaric\Gallery\Support\GalleryPermissions;

final class AttachGalleryToModelAction
{
    public function handle(
        Model $model,
        Gallery $gallery,
        string $collection = 'images',
        bool $allowReplace = false,
        bool $emptyOnly = true,
    ): ActionResult {
        GalleryPermissions::authorize('attach');

        if (! method_exists($model, 'attachStandaloneGallery')) {
            return ActionResult::error(
                message: __('Model ne podržava povezivanje galerija.'),
                code: 'gallery_model_not_supported',
            );
        }

        try {
            $attached = DB::transaction(static function () use ($model, $gallery, $collection, $allowReplace, $emptyOnly): Gallery {
                $model->newQuery()
                    ->whereKey($model->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                /** @var Gallery $lockedGallery */
                $lockedGallery = Gallery::query()
                    ->whereKey($gallery->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                return $model->attachStandaloneGallery(
                    $lockedGallery,
                    $collection,
                    $allowReplace,
                    $emptyOnly,
                );
            });
        } catch (InvalidArgumentException $exception) {
            return ActionResult::error(
                message: self::attachmentErrorMessage($exception->getMessage()),
                errors: ['selectedGalleryUuid' => [self::attachmentErrorMessage($exception->getMessage())]],
                code: 'gallery_attach_failed',
            );
        }

        GalleryAttachedToModel::dispatch($attached, $model::class, $model->getKey(), $collection);

        return ActionResult::success(
            message: __('Galerija je dodijeljena.'),
            data: $attached,
            code: 'gallery_attached',
        );
    }

    private static function attachmentErrorMessage(string $message): string
    {
        return match ($message) {
            'The model must be saved before attaching a gallery.' => __('Zapis mora biti spremljen prije povezivanja galerije.'),
            'Only standalone galleries can be attached.' => __('Moguće je povezati samo samostalne galerije.'),
            'Only empty standalone galleries can be attached.' => __('Moguće je povezati samo prazne samostalne galerije.'),
            'The model already has a gallery for this collection.' => __('Zapis već ima galeriju za ovu kolekciju.'),
            default => __('Galeriju nije moguće povezati.'),
        };
    }
}
