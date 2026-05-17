<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Resolvers;

use IvanBaric\Gallery\Contracts\TenantResolver;

final class VeloraCurrentTeamResolver implements TenantResolver
{
    public function enabled(): bool
    {
        return (bool) config('gallery.tenancy.enabled', false);
    }

    public function id(): int|string|null
    {
        if (! $this->enabled() || ! function_exists('current_team_id')) {
            return null;
        }

        try {
            return current_team_id();
        } catch (\Throwable) {
            return null;
        }
    }

    public function uuid(): ?string
    {
        if (! $this->enabled() || ! function_exists('team')) {
            return null;
        }

        try {
            $team = team();

            return $team?->uuid === null ? null : (string) $team->uuid;
        } catch (\Throwable) {
            return null;
        }
    }

    public function type(): ?string
    {
        return $this->enabled() ? 'team' : null;
    }
}
