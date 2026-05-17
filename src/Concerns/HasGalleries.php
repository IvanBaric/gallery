<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Concerns;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use IvanBaric\Gallery\Models\Gallery;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait HasGalleries
{
    public static function bootHasGalleries(): void
    {
        static::deleting(function ($model): void {
            foreach ($model->galleries as $gallery) {
                $gallery->clearMediaCollection((string) $gallery->collection_name);
                $gallery->delete();
            }
        });
    }

    public function galleries(): MorphMany
    {
        return $this->morphMany(config('gallery.models.gallery', Gallery::class), 'galleryable');
    }

    public function gallery(string $collection = 'images'): ?Gallery
    {
        /** @var Gallery|null $gallery */
        $gallery = $this->galleries()
            ->where('collection_name', $collection)
            ->forCurrentTenant()
            ->first();

        return $gallery;
    }

    public function getOrCreateGallery(string $collection = 'images', array $attributes = []): Gallery
    {
        $gallery = $this->gallery($collection);

        if ($gallery) {
            return $gallery;
        }

        /** @var Gallery $gallery */
        $gallery = $this->galleries()->create(array_merge([
            'collection_name' => $collection,
            'title' => $this->defaultGalleryTitle(),
        ], $attributes));

        return $gallery;
    }

    public function attachStandaloneGallery(Gallery|int|string $gallery, string $collection = 'images', bool $replace = false, bool $emptyOnly = true): Gallery
    {
        if (! $this->exists || $this->getKey() === null) {
            throw new InvalidArgumentException('The model must be saved before attaching a gallery.');
        }

        $gallery = $this->resolveGalleryForAttachment($gallery);

        if ($gallery->galleryable_type !== null || $gallery->galleryable_id !== null) {
            throw new InvalidArgumentException('Only standalone galleries can be attached.');
        }

        if ($emptyOnly && $gallery->getMedia($gallery->collection_name)->isNotEmpty()) {
            throw new InvalidArgumentException('Only empty standalone galleries can be attached.');
        }

        $current = $this->gallery($collection);

        if ($current && (string) $current->getKey() !== (string) $gallery->getKey()) {
            if (! $replace) {
                throw new InvalidArgumentException('The model already has a gallery for this collection.');
            }

            $current->forceFill([
                'galleryable_type' => null,
                'galleryable_id' => null,
            ])->save();
        }

        $gallery->forceFill([
            'galleryable_type' => $this->getMorphClass(),
            'galleryable_id' => $this->getKey(),
            'collection_name' => $collection,
            'title' => filled($gallery->title) ? $gallery->title : $this->defaultGalleryTitle(),
        ])->save();

        $this->unsetRelation('galleries');

        return $gallery->refresh();
    }

    public function galleryMedia(string $collection = 'images'): Collection
    {
        $gallery = $this->gallery($collection);

        if ($gallery && $gallery->getMedia($collection)->isNotEmpty()) {
            return $gallery->getMedia($collection);
        }

        if (method_exists($this, 'getMedia')) {
            return $this->getMedia($collection);
        }

        return collect();
    }

    public function galleryMediaCount(string $collection = 'images'): int
    {
        return $this->galleryMedia($collection)->count();
    }

    public function galleryFeaturedMedia(string $collection = 'images'): ?Media
    {
        $gallery = $this->gallery($collection);

        if ($gallery) {
            $featured = $gallery->featuredOrFirstMedia();

            if ($featured) {
                return $featured;
            }
        }

        if (method_exists($this, 'getFirstMedia')) {
            return $this->getFirstMedia($collection);
        }

        return null;
    }

    public function galleryImageUrl(string $collection = 'images', string $conversion = 'large'): ?string
    {
        $media = $this->galleryFeaturedMedia($collection);

        if (! $media) {
            return null;
        }

        if ($conversion !== '' && method_exists($media, 'hasGeneratedConversion') && ! $media->hasGeneratedConversion($conversion)) {
            return $media->getUrl();
        }

        return $media->getUrl($conversion);
    }

    public function migrateMediaCollectionToGallery(string $collection = 'images'): int
    {
        if (! method_exists($this, 'getMedia')) {
            return 0;
        }

        $legacyMedia = $this->getMedia($collection);

        if ($legacyMedia->isEmpty()) {
            return 0;
        }

        $gallery = $this->getOrCreateGallery($collection);
        $galleryMorphClass = $gallery->getMorphClass();

        foreach ($legacyMedia as $media) {
            $media->forceFill([
                'model_type' => $galleryMorphClass,
                'model_id' => $gallery->getKey(),
                'collection_name' => $collection,
            ])->save();
        }

        $gallery->load('media');

        return $legacyMedia->count();
    }

    protected function defaultGalleryTitle(): string
    {
        foreach (['title', 'name', 'display_name', 'email'] as $attribute) {
            if (filled($this->getAttribute($attribute))) {
                return (string) $this->getAttribute($attribute);
            }
        }

        return class_basename($this).' #'.$this->getKey();
    }

    protected function resolveGalleryForAttachment(Gallery|int|string $gallery): Gallery
    {
        if ($gallery instanceof Gallery) {
            return $gallery;
        }

        $query = Gallery::query()->forCurrentTenant();

        if (is_int($gallery) || ctype_digit((string) $gallery)) {
            $resolved = $query->whereKey($gallery)->first();
        } else {
            $resolved = $query->where('uuid', (string) $gallery)->first();
        }

        if (! $resolved) {
            throw (new ModelNotFoundException())->setModel(Gallery::class, [$gallery]);
        }

        return $resolved;
    }
}
