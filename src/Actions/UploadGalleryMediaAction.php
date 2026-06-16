<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Actions;

use Illuminate\Support\Facades\DB;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Gallery\Events\GalleryMediaUploaded;
use IvanBaric\Gallery\Models\Gallery;
use IvanBaric\Gallery\Support\GalleryPermissions;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class UploadGalleryMediaAction
{
    /**
     * @param  array<int, TemporaryUploadedFile>  $uploads
     */
    public function handle(Gallery $gallery, array $uploads, ?string $collection = null): ActionResult
    {
        GalleryPermissions::authorize('upload');

        if ($uploads === []) {
            return ActionResult::error(__('Odaberite barem jednu fotografiju.'), 'gallery_upload_empty');
        }

        $collection ??= (string) $gallery->collection_name;
        $mediaIds = [];

        DB::transaction(static function () use ($gallery, $uploads, $collection, &$mediaIds): void {
            foreach ($uploads as $upload) {
                $media = $gallery
                    ->addMedia($upload->getRealPath())
                    ->usingFileName($upload->hashName())
                    ->usingName(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME) ?: $upload->hashName())
                    ->withCustomProperties([
                        'alt' => '',
                        'title' => '',
                        'caption' => '',
                        'description' => '',
                        'credit' => '',
                        'source_url' => '',
                        'license' => '',
                        'is_decorative' => false,
                    ])
                    ->toMediaCollection($collection);

                $mediaIds[] = (int) $media->id;
            }

            $gallery->touch();
        });

        event(new GalleryMediaUploaded($gallery->refresh(), $mediaIds));

        return ActionResult::success(
            message: __('Fotografije su spremljene.'),
            data: ['media_ids' => $mediaIds],
        );
    }
}
