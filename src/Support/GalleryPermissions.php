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
            'name' => 'Galerije',
            'slug' => 'gallery',
            'label' => 'Galerije',
            'description' => 'Upravljanje galerijama, fotografijama, SEO podacima i generiranim veličinama.',
            'icon' => 'photo',
            'sort_order' => $sortOrder,
            'items' => [
                ['name' => 'Pregled', 'slug' => 'view', 'code' => self::VIEW, 'label' => 'Pregled galerija', 'sort_order' => 10],
                ['name' => 'Kreiranje', 'slug' => 'create', 'code' => self::CREATE, 'label' => 'Kreiranje galerija', 'sort_order' => 20],
                ['name' => 'Uređivanje', 'slug' => 'update', 'code' => self::UPDATE, 'label' => 'Uređivanje galerija', 'sort_order' => 30],
                ['name' => 'Upload', 'slug' => 'upload', 'code' => self::UPLOAD, 'label' => 'Dodavanje fotografija', 'sort_order' => 40],
                ['name' => 'Dodjela', 'slug' => 'attach', 'code' => self::ATTACH, 'label' => 'Dodjela samostalnih galerija modelima', 'sort_order' => 45],
                ['name' => 'SEO podaci', 'slug' => 'seo', 'code' => self::SEO, 'label' => 'Uređivanje SEO podataka slika', 'sort_order' => 50],
                ['name' => 'Regeneriranje', 'slug' => 'regenerate', 'code' => self::REGENERATE, 'label' => 'Regeneriranje veličina slika', 'sort_order' => 60],
                ['name' => 'Postavke', 'slug' => 'settings', 'code' => self::SETTINGS, 'label' => 'Upravljanje postavkama galerije', 'sort_order' => 70],
                ['name' => 'Brisanje', 'slug' => 'delete', 'code' => self::DELETE, 'label' => 'Brisanje galerija i fotografija', 'sort_order' => 80],
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
