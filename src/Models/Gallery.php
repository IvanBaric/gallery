<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use IvanBaric\Gallery\Contracts\TenantResolver;
use IvanBaric\Gallery\Exceptions\TenantNotResolvedException;
use IvanBaric\Gallery\Support\GallerySettings;
use Spatie\Image\Enums\CropPosition;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

class Gallery extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'custom_properties' => 'array',
        ];
    }

    public function getTable(): string
    {
        return (string) config('gallery.tables.galleries', 'galleries');
    }

    public function galleryable(): MorphTo
    {
        return $this->morphTo();
    }

    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(config('gallery.models.media'), 'featured_media_id');
    }

    public function scopeForCurrentTenant(Builder $query): Builder
    {
        $resolver = app(TenantResolver::class);

        if (! $resolver->enabled()) {
            return $query;
        }

        $tenantId = $resolver->id();

        if ($tenantId === null) {
            if ((bool) config('gallery.tenancy.fail_when_unresolved', false)) {
                throw TenantNotResolvedException::make();
            }

            return $query;
        }

        return $query->where((string) config('gallery.tenancy.id_column', 'tenant_id'), (string) $tenantId);
    }

    public function displayTitle(): string
    {
        if (filled($this->title)) {
            return (string) $this->title;
        }

        $owner = $this->galleryable;

        foreach (['title', 'name', 'display_name', 'email'] as $attribute) {
            if ($owner && filled($owner->getAttribute($attribute))) {
                return (string) $owner->getAttribute($attribute);
            }
        }

        return __('Galerija #:id', ['id' => $this->getKey()]);
    }

    public function ownerLabel(): string
    {
        $owner = $this->galleryable;

        if (! $owner) {
            return __('Samostalna galerija');
        }

        return class_basename($owner).' #'.$owner->getKey();
    }

    public function featuredOrFirstMedia(): ?BaseMedia
    {
        if ($this->featured_media_id) {
            $featured = $this->getMedia($this->collection_name)->firstWhere('id', (int) $this->featured_media_id);

            if ($featured) {
                return $featured;
            }
        }

        return $this->getMedia($this->collection_name)->first();
    }

    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection((string) ($this->collection_name ?: config('gallery.default_collection', 'images')))
            ->useDisk((string) config('gallery.disk', config('media-library.disk_name', 'public')));
    }

    public function registerMediaConversions(?BaseMedia $media = null): void
    {
        foreach (GallerySettings::imageSizes() as $name => $size) {
            if (! $size['enabled'] || ! $size['width']) {
                continue;
            }

            $conversion = $this->addMediaConversion($name);

            if ($size['fit'] === 'crop' && $size['height']) {
                $conversion->crop($size['width'], $size['height'], CropPosition::Center);
            } else {
                $conversion->fit(Fit::Contain, $size['width'], $size['height'] ?? $size['width']);
            }

            if ((bool) config('gallery.conversions.generate_responsive_images', false)) {
                $conversion->withResponsiveImages();
            }

            if ((bool) config('gallery.conversions.queued', false)) {
                $conversion->queued();
            } else {
                $conversion->nonQueued();
            }
        }
    }

    protected static function booted(): void
    {
        static::creating(function (self $gallery): void {
            if (blank($gallery->uuid)) {
                $gallery->uuid = (string) Str::uuid();
            }

            if (blank($gallery->collection_name)) {
                $gallery->collection_name = (string) config('gallery.default_collection', 'images');
            }

            $resolver = app(TenantResolver::class);

            if (! $resolver->enabled()) {
                return;
            }

            $tenantId = $resolver->id();

            if ($tenantId === null) {
                if ((bool) config('gallery.tenancy.fail_when_unresolved', false)) {
                    throw TenantNotResolvedException::make();
                }

                return;
            }

            $gallery->setAttribute((string) config('gallery.tenancy.id_column', 'tenant_id'), (string) $tenantId);
            $gallery->setAttribute((string) config('gallery.tenancy.uuid_column', 'tenant_uuid'), $resolver->uuid());
            $gallery->setAttribute((string) config('gallery.tenancy.type_column', 'tenant_type'), $resolver->type());
        });
    }
}
