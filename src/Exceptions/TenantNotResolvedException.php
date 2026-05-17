<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Exceptions;

use RuntimeException;

final class TenantNotResolvedException extends RuntimeException
{
    public static function make(): self
    {
        return new self('Gallery tenancy is enabled, but no tenant could be resolved.');
    }
}
