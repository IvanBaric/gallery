<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Resolvers;

use IvanBaric\Corexis\Contracts\TenantResolver as CorexisTenantResolverContract;
use IvanBaric\Gallery\Contracts\TenantResolver;

final readonly class CorexisTenantResolver implements TenantResolver
{
    public function __construct(
        private CorexisTenantResolverContract $resolver,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('gallery.tenancy.enabled', false) && $this->resolver->enabled();
    }

    public function id(): int|string|null
    {
        return $this->enabled() ? $this->resolver->id() : null;
    }

    public function uuid(): ?string
    {
        return $this->enabled() ? $this->resolver->uuid() : null;
    }

    public function type(): ?string
    {
        return $this->enabled() ? $this->resolver->type() : null;
    }
}
