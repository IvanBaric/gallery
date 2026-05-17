<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Models;

use Illuminate\Database\Eloquent\Builder;
use IvanBaric\Gallery\Contracts\TenantResolver;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

class Media extends SpatieMedia
{
    public function scopeForCurrentTenant(Builder $query): Builder
    {
        $resolver = app(TenantResolver::class);

        if (! $resolver->enabled()) {
            return $query;
        }

        $galleryClass = (string) config('gallery.models.gallery', Gallery::class);
        $galleryMorphClass = (new $galleryClass)->getMorphClass();

        return $query
            ->where('model_type', $galleryMorphClass)
            ->whereHasMorph('model', [$galleryClass], fn (Builder $query): Builder => $query->forCurrentTenant());
    }

    public function gallery(): ?Gallery
    {
        $model = $this->model;

        return $model instanceof Gallery ? $model : null;
    }

    public function isAccessibleForCurrentTenant(): bool
    {
        $resolver = app(TenantResolver::class);

        if (! $resolver->enabled()) {
            return true;
        }

        $gallery = $this->gallery();

        if (! $gallery) {
            return true;
        }

        return (string) $gallery->getAttribute((string) config('gallery.tenancy.id_column', 'tenant_id')) === (string) $resolver->id();
    }

    public function altText(?string $fallback = null): string
    {
        $alt = $this->getCustomProperty('alt');

        if (is_string($alt) && filled($alt)) {
            return $alt;
        }

        return $fallback ?: $this->name;
    }

    public function secureUrl(string $conversion = ''): string
    {
        return route((string) config('gallery.routes.media_route_name', 'gallery.media.show'), [
            'media' => $this->uuid,
            'conversion' => $conversion ?: null,
        ]);
    }
}
