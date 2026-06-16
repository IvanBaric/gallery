<?php

declare(strict_types=1);

use IvanBaric\Gallery\Models\Gallery;
use IvanBaric\Gallery\Models\Media;
use IvanBaric\Gallery\Resolvers\CorexisTenantResolver;
use IvanBaric\Gallery\Support\GalleryPermissions;

return [
    'models' => [
        'gallery' => Gallery::class,
        'media' => Media::class,
    ],

    'tables' => [
        'galleries' => 'galleries',
        'settings' => 'gallery_settings',
    ],

    'media' => [
        'register_media_model' => true,
    ],

    'routes' => [
        'enabled' => true,
        'prefix' => 'app',
        'path' => 'galleries',
        'name' => 'admin.galleries.',
        'middleware' => ['web', 'auth', 'verified'],
        'media_path' => 'gallery/media',
        'media_route_name' => 'gallery.media.show',
        'media_middleware' => ['web', 'auth'],
    ],

    'permissions' => [
        'enabled' => env('GALLERY_PERMISSIONS_ENABLED', false),
        'actions' => GalleryPermissions::defaults(),
        'groups' => GalleryPermissions::groups(),
    ],

    'velora_permissions' => [
        [
            'name' => 'gallery',
            'slug' => 'gallery',
            'label' => 'gallery::permissions.group',
            'description' => 'gallery::permissions.description',
            'icon' => 'images',
            'sort_order' => 40,
            'items' => [
                ['name' => 'View', 'slug' => 'view', 'code' => 'gallery.view', 'label' => 'gallery::permissions.view', 'sort_order' => 10],
                ['name' => 'Create', 'slug' => 'create', 'code' => 'gallery.create', 'label' => 'gallery::permissions.create', 'sort_order' => 20],
                ['name' => 'Update', 'slug' => 'update', 'code' => 'gallery.update', 'label' => 'gallery::permissions.update', 'sort_order' => 30],
                ['name' => 'Delete', 'slug' => 'delete', 'code' => 'gallery.delete', 'label' => 'gallery::permissions.delete', 'sort_order' => 40],
                ['name' => 'Upload media', 'slug' => 'upload', 'code' => GalleryPermissions::UPLOAD, 'label' => 'gallery::permissions.media_upload', 'sort_order' => 50],
                ['name' => 'Attach galleries', 'slug' => 'attach', 'code' => GalleryPermissions::ATTACH, 'label' => 'gallery::permissions.attach', 'sort_order' => 60],
                ['name' => 'Update media metadata', 'slug' => 'seo', 'code' => GalleryPermissions::SEO, 'label' => 'gallery::permissions.media_meta', 'sort_order' => 70],
            ],
        ],
    ],

    'deletion' => [
        /*
         * Password confirmation modes:
         * - non_empty: require a password only when the gallery contains media.
         * - always: require a password for every gallery deletion.
         * - never: delete from the confirmation modal without a password.
         */
        'password_confirmation' => env('GALLERY_DELETE_PASSWORD_CONFIRMATION', 'non_empty'),
    ],

    'disk' => env('GALLERY_DISK', env('MEDIA_DISK', 'public')),
    'default_collection' => 'images',

    'validation' => [
        'max_files' => 30,
        'max_file_size_kb' => 3072,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        'min_width' => null,
        'min_height' => null,
    ],

    'contexts' => [
        'vehicle' => [
            'label' => 'Vozilo',
            'max_files' => 30,
            'max_file_size_kb' => 3072,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        ],
        'purchase_request' => [
            'label' => 'Otkup',
            'max_files' => 30,
            'max_file_size_kb' => 3072,
            'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        ],
    ],

    'sizes' => [
        'thumbnail' => [
            'label' => 'Thumbnail',
            'width' => 150,
            'height' => 150,
            'fit' => 'crop',
            'enabled' => true,
        ],
        'thumb' => [
            'label' => 'Thumb',
            'width' => 600,
            'height' => 400,
            'fit' => 'crop',
            'enabled' => true,
        ],
        'medium' => [
            'label' => 'Medium',
            'width' => 300,
            'height' => 300,
            'fit' => 'contain',
            'enabled' => true,
        ],
        'medium_large' => [
            'label' => 'Medium Large',
            'width' => 768,
            'height' => null,
            'fit' => 'contain',
            'enabled' => true,
        ],
        'large' => [
            'label' => 'Large',
            'width' => 1024,
            'height' => 1024,
            'fit' => 'contain',
            'enabled' => true,
        ],
        'xlarge' => [
            'label' => 'Extra Large',
            'width' => 1536,
            'height' => 1536,
            'fit' => 'contain',
            'enabled' => true,
        ],
        'admin_thumb' => [
            'label' => 'Admin Thumb',
            'width' => 600,
            'height' => 400,
            'fit' => 'crop',
            'enabled' => true,
        ],
    ],

    'conversions' => [
        'queued' => false,
        'generate_responsive_images' => false,
    ],

    'tenancy' => [
        'enabled' => env('GALLERY_TENANCY_ENABLED', false),
        'resolver' => CorexisTenantResolver::class,
        'id_column' => env('GALLERY_TENANT_ID_COLUMN', env('COREXIS_TENANT_ID_COLUMN', 'team_id')),
        'uuid_column' => env('GALLERY_TENANT_UUID_COLUMN', 'tenant_uuid'),
        'type_column' => env('GALLERY_TENANT_TYPE_COLUMN', 'tenant_type'),
        'fail_when_unresolved' => env('GALLERY_TENANCY_FAIL_WHEN_UNRESOLVED', false),
    ],
];
