<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
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
}
