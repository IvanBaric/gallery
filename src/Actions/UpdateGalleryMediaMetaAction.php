<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use IvanBaric\Corexis\Concerns\UsesOptimisticLocking;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Gallery\Events\GalleryMediaMetaUpdated;
use IvanBaric\Gallery\Models\Gallery;
use IvanBaric\Gallery\Support\GalleryPermissions;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

final class UpdateGalleryMediaMetaAction
{
    use UsesOptimisticLocking;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Gallery $gallery, SpatieMedia $media, array $data): ActionResult
    {
        GalleryPermissions::authorize('seo');

        if ((string) $media->model_type !== (string) $gallery->getMorphClass() || (int) $media->model_id !== (int) $gallery->getKey()) {
            return ActionResult::error(__('Fotografija nije pronađena u galeriji.'), 'gallery_media_not_found');
        }

        $validator = Validator::make($data, [
            'alt' => ['nullable', 'string', 'max:180'],
            'title' => ['nullable', 'string', 'max:180'],
            'caption' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'credit' => ['nullable', 'string', 'max:180'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'license' => ['nullable', 'string', 'max:180'],
            'is_decorative' => ['boolean'],
            'lock_version' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return ActionResult::error(
                message: __('Provjerite podatke fotografije i pokušajte ponovno.'),
                code: 'validation_failed',
                errors: $validator->errors()->toArray(),
            );
        }

        $form = $validator->validated();
        $expectedLockVersion = $this->pullExpectedLockVersion($form);

        $saved = DB::transaction(function () use ($form, $media, $gallery, $expectedLockVersion): bool {
            /** @var SpatieMedia $lockedMedia */
            $lockedMedia = $media->newQuery()
                ->whereKey($media->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedMedia->name = filled($form['title'] ?? null) ? (string) $form['title'] : $lockedMedia->name;
            $lockedMedia->custom_properties = array_merge($lockedMedia->custom_properties ?? [], [
                'alt' => ($form['alt'] ?? '') ?: null,
                'title' => ($form['title'] ?? '') ?: null,
                'caption' => ($form['caption'] ?? '') ?: null,
                'description' => ($form['description'] ?? '') ?: null,
                'credit' => ($form['credit'] ?? '') ?: null,
                'source_url' => ($form['source_url'] ?? '') ?: null,
                'license' => ($form['license'] ?? '') ?: null,
                'is_decorative' => (bool) ($form['is_decorative'] ?? false),
            ]);
            $lockedMedia->save();

            return $this->saveWithOptimisticLock($gallery, [], $expectedLockVersion);
        });

        if (! $saved) {
            return $this->staleModelResult();
        }

        event(new GalleryMediaMetaUpdated($gallery->refresh(), (int) $media->id));

        return ActionResult::success(__('Podaci fotografije su ažurirani.'), $media->refresh());
    }
}
