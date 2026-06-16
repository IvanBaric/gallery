<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Gallery\Events\GalleryUpdated;
use IvanBaric\Gallery\Models\Gallery;
use IvanBaric\Gallery\Support\GalleryPermissions;

final class UpdateGalleryAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Gallery $gallery, array $data): ActionResult
    {
        GalleryPermissions::authorize('update');

        $validator = Validator::make($data, [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return ActionResult::error(
                message: __('Provjerite podatke galerije i pokušajte ponovno.'),
                code: 'validation_failed',
                errors: $validator->errors()->toArray(),
            );
        }

        DB::transaction(static function () use ($gallery, $validator): void {
            $gallery->forceFill($validator->validated())->save();
        });

        event(new GalleryUpdated($gallery->refresh()));

        return ActionResult::success(
            message: __('Galerija je ažurirana.'),
            data: $gallery,
        );
    }
}
