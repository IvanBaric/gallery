<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Contracts;

interface TenantResolver
{
    public function enabled(): bool;

    public function id(): int|string|null;

    public function uuid(): ?string;

    public function type(): ?string;
}
