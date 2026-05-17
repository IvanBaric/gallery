<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Resolvers;

use IvanBaric\Gallery\Contracts\TenantResolver;

final class NullTenantResolver implements TenantResolver
{
    public function enabled(): bool
    {
        return false;
    }

    public function id(): int|string|null
    {
        return null;
    }

    public function uuid(): ?string
    {
        return null;
    }

    public function type(): ?string
    {
        return null;
    }
}
