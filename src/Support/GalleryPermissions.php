<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Support;

use Illuminate\Support\Facades\Gate;

final class GalleryPermissions
{
    public const VIEW = 'gallery.view';

    public const CREATE = 'gallery.create';

    public const UPDATE = 'gallery.update';

    public const UPLOAD = 'gallery.upload';

    public const ATTACH = 'gallery.attach';

    public const DELETE = 'gallery.delete';

    public const REGENERATE = 'gallery.regenerate';

    public const SETTINGS = 'gallery.settings';

    public const SEO = 'gallery.seo';

    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'view' => self::VIEW,
            'create' => self::CREATE,
            'update' => self::UPDATE,
            'upload' => self::UPLOAD,
            'attach' => self::ATTACH,
            'delete' => self::DELETE,
            'regenerate' => self::REGENERATE,
            'settings' => self::SETTINGS,
            'seo' => self::SEO,
        ];
    }

    /**
     * Permission dictionary compatible with ivanbaric/velora.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function groups(int $sortOrder = 85): array
    {
        return [[
            'name' => 'gallery',
            'slug' => 'gallery',
            'label' => 'gallery::permissions.group',
            'description' => 'gallery::permissions.description',
            'icon' => 'photo',
            'sort_order' => $sortOrder,
            'items' => [
                ['name' => 'view', 'slug' => 'view', 'code' => self::VIEW, 'label' => 'gallery::permissions.view', 'sort_order' => 10],
                ['name' => 'create', 'slug' => 'create', 'code' => self::CREATE, 'label' => 'gallery::permissions.create', 'sort_order' => 20],
                ['name' => 'update', 'slug' => 'update', 'code' => self::UPDATE, 'label' => 'gallery::permissions.update', 'sort_order' => 30],
                ['name' => 'upload', 'slug' => 'upload', 'code' => self::UPLOAD, 'label' => 'gallery::permissions.media_upload', 'sort_order' => 40],
                ['name' => 'attach', 'slug' => 'attach', 'code' => self::ATTACH, 'label' => 'gallery::permissions.attach', 'sort_order' => 45],
                ['name' => 'seo', 'slug' => 'seo', 'code' => self::SEO, 'label' => 'gallery::permissions.media_meta', 'sort_order' => 50],
                ['name' => 'regenerate', 'slug' => 'regenerate', 'code' => self::REGENERATE, 'label' => 'gallery::permissions.regenerate', 'sort_order' => 60],
                ['name' => 'settings', 'slug' => 'settings', 'code' => self::SETTINGS, 'label' => 'gallery::permissions.settings', 'sort_order' => 70],
                ['name' => 'delete', 'slug' => 'delete', 'code' => self::DELETE, 'label' => 'gallery::permissions.delete', 'sort_order' => 80],
            ],
        ]];
    }

    public static function enabled(): bool
    {
        return (bool) config('gallery.permissions.enabled', false);
    }

    public static function code(string $action): ?string
    {
        $configured = config("gallery.permissions.actions.$action");

        if (is_string($configured) && filled($configured)) {
            return $configured;
        }

        return self::defaults()[$action] ?? null;
    }

    public static function allows(mixed $user, string $action): bool
    {
        if (! self::enabled()) {
            return true;
        }

        $permission = self::code($action);

        if (! $permission) {
            return true;
        }

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasPermission')) {
            return (bool) $user->hasPermission($permission);
        }

        return Gate::forUser($user)->allows($permission);
    }

    public static function authorize(string $action): void
    {
        abort_unless(self::allows(auth()->user(), $action), 403);
    }
}
