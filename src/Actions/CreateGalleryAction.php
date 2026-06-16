<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Gallery\Events\GalleryCreated;
use IvanBaric\Gallery\Models\Gallery;
use IvanBaric\Gallery\Support\GalleryPermissions;

final class CreateGalleryAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): ActionResult
    {
        GalleryPermissions::authorize('create');

        $validator = Validator::make($data, [
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'collection_name' => ['nullable', 'string', 'max:120'],
        ], [
            'title.required' => __('Unesite naziv galerije.'),
            'title.max' => __('Naziv galerije može imati najviše :max znakova.'),
        ]);

        if ($validator->fails()) {
            return ActionResult::error(
                message: __('Provjerite podatke galerije i pokušajte ponovno.'),
                code: 'validation_failed',
                errors: $validator->errors()->toArray(),
            );
        }

        $validated = $validator->validated();

        /** @var Gallery $gallery */
        $gallery = DB::transaction(static fn (): Gallery => Gallery::query()->create([
            'title' => trim((string) $validated['title']),
            'description' => $validated['description'] ?? null,
            'collection_name' => $validated['collection_name'] ?? (string) config('gallery.default_collection', 'images'),
        ]));

        event(new GalleryCreated($gallery->refresh()));

        return ActionResult::success(
            message: __('Galerija je kreirana.'),
            data: $gallery,
        );
    }
}
