<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
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

    public function scopeStandalone(Builder $query): Builder
    {
        return $query
            ->whereNull('galleryable_type')
            ->whereNull('galleryable_id');
    }

    public function scopeAttached(Builder $query): Builder
    {
        return $query
            ->whereNotNull('galleryable_type')
            ->whereNotNull('galleryable_id');
    }

    public function scopeForCollection(Builder $query, string $collection): Builder
    {
        return $query->where('collection_name', $collection);
    }

    public function scopeEmpty(Builder $query): Builder
    {
        return $query->whereDoesntHave('media');
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

        return $query->where((string) config('gallery.tenancy.id_column', 'team_id'), (string) $tenantId);
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

    public function ownerTypeLabel(): string
    {
        if (! $this->galleryable_type) {
            return __('Samostalna');
        }

        return match (class_basename((string) $this->galleryable_type)) {
            'Car' => __('Vozilo'),
            'VehiclePurchaseRequest' => __('Otkup'),
            default => class_basename((string) $this->galleryable_type),
        };
    }

    public function lastRegeneratedAt(): ?Carbon
    {
        return $this->customPropertyDate('last_regenerated_at');
    }

    public function regenerationQueuedAt(): ?Carbon
    {
        return $this->customPropertyDate('regeneration_queued_at');
    }

    public function markRegenerationQueued(?int $mediaCount = null): void
    {
        $this->mergeCustomProperties([
            'regeneration_queued_at' => now()->toISOString(),
            'regeneration_queued_media_count' => $mediaCount,
        ]);
    }

    public function markRegenerated(int $mediaCount): void
    {
        $this->mergeCustomProperties([
            'last_regenerated_at' => now()->toISOString(),
            'last_regenerated_media_count' => $mediaCount,
            'regeneration_queued_at' => null,
            'regeneration_queued_media_count' => null,
        ]);
    }

    public function seoCompleteMediaCount(): int
    {
        return $this
            ->getMedia($this->collection_name)
            ->filter(fn (BaseMedia $media): bool => $this->mediaHasSeo($media))
            ->count();
    }

    public function mediaHasSeo(BaseMedia $media): bool
    {
        return (bool) $media->getCustomProperty('is_decorative', false)
            || filled($media->getCustomProperty('alt'));
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

            $gallery->setAttribute((string) config('gallery.tenancy.id_column', 'team_id'), (string) $tenantId);
            $gallery->setAttribute((string) config('gallery.tenancy.uuid_column', 'tenant_uuid'), $resolver->uuid());
            $gallery->setAttribute((string) config('gallery.tenancy.type_column', 'tenant_type'), $resolver->type());
        });
    }

    private function customPropertyDate(string $key): ?Carbon
    {
        $value = $this->custom_properties[$key] ?? null;

        if (! is_string($value) || blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function mergeCustomProperties(array $properties): void
    {
        $this->forceFill([
            'custom_properties' => array_merge($this->custom_properties ?? [], $properties),
        ])->save();
    }
}
