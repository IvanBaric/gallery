# Gallery

Reusable Laravel gallery manager built on top of Spatie Media Library, Livewire 4, Flux UI, and `ivanbaric/admin-ui`.

The package gives an admin application a shared gallery layer that can be attached to any Eloquent model. It keeps images in one gallery model, supports SEO metadata, featured images, ordering, protected media access, WordPress-like image sizes, settings, regeneration, and publishable views.

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13
- Livewire 4
- Flux UI
- Spatie Media Library 11
- `ivanbaric/admin-ui`

## Installation

Install through Composer:

```bash
composer require ivanbaric/gallery
```

Run migrations:

```bash
php artisan migrate
```

Laravel auto-discovers the service provider.

## Configuration

Publish the configuration when an application needs custom rules, image sizes, routes, tenancy, or model overrides:

```bash
php artisan vendor:publish --tag=gallery-config
```

Common options live in `config/gallery.php`:

- `models.gallery` and `models.media` override the package models.
- `validation` defines default max files, file size, file extensions, and dimensions.
- `contexts` override validation per use case, such as vehicles or purchase requests.
- `sizes` defines generated image conversions.
- `tenancy` enables current-tenant filtering for galleries and media.
- `routes` controls the admin gallery index and protected media route.

The package can register its media model automatically:

```php
'media' => [
    'register_media_model' => true,
],
```

If the host application already configures Spatie Media Library directly, set that option to `false` and point `media-library.media_model` to your own class.

## Tailwind

Add package views to Tailwind sources:

```css
@source '../../vendor/ivanbaric/gallery/resources/views/**/*.blade.php';
```

For a local path repository:

```css
@source '../../packages/ivanbaric/gallery/resources/views/**/*.blade.php';
```

If the application uses `ivanbaric/admin-ui` from a local path, keep its CSS import and source entries as well.

## Model Setup

Add the trait to any model that needs galleries:

```php
use IvanBaric\Gallery\Concerns\HasGalleries;

class Car extends Model implements HasMedia
{
    use HasGalleries;
}
```

The trait adds:

- `galleries()`
- `gallery($collection = 'images')`
- `getOrCreateGallery($collection = 'images')`
- `galleryMedia($collection = 'images')`
- `galleryMediaCount($collection = 'images')`
- `galleryFeaturedMedia($collection = 'images')`
- `galleryImageUrl($collection = 'images', $conversion = 'large')`
- `migrateMediaCollectionToGallery($collection = 'images')`

The helper methods keep a fallback to legacy Spatie media collections so existing screens can be migrated gradually.

## Blade Manager

Render a gallery anywhere in an admin form:

```blade
<x-gallery::manager
    :model="$car"
    collection="images"
    context="vehicle"
    :title="__('Fotografije')"
    :description="__('Upravljajte fotografijama, SEO podacima, redoslijedom i istaknutom slikom.')"
/>
```

For a purchase request:

```blade
<x-gallery::manager
    :model="$purchaseRequest"
    collection="images"
    context="purchase_request"
    :title="__('Galerija vozila')"
/>
```

The manager supports:

- Uploading multiple images with configurable validation.
- Drag-and-drop ordering through Livewire sorting.
- Featured image selection.
- SEO metadata: alt text, title, caption, description, credit, source URL, license, and decorative image flag.
- Delete confirmation modal.
- Regenerating conversions for the current gallery.

## Admin Gallery Index

The package registers an admin index route by default:

```text
GET /app/galleries
```

The route name is:

```text
admin.galleries.index
```

This screen lists galleries from newest to oldest, exposes global settings, and can regenerate conversions.

## Image Sizes

Default sizes are intentionally close to WordPress conventions:

- `thumbnail` 150x150 crop
- `medium` 300x300 contain
- `medium_large` 768px wide
- `large` 1024x1024 contain
- `xlarge` 1536x1536 contain
- `admin_thumb` 600x400 crop

The package also includes `thumb` as a practical 600x400 compatibility conversion for admin and catalog cards.

## Regeneration

Regenerate from the admin UI, or use Spatie Media Library directly:

```bash
php artisan media-library:regenerate "IvanBaric\\Gallery\\Models\\Gallery"
```

## Migrating Existing Media

If an application already stores images directly on a model, migrate them into galleries:

```bash
php artisan gallery:migrate-model-media "App\\Models\\Car" images
```

The command moves existing media rows to the package gallery model while preserving the media files.

## Tenancy

Tenancy is disabled by default. Enable it in published config:

```php
'tenancy' => [
    'enabled' => true,
    'resolver' => App\Support\CurrentTenantResolver::class,
],
```

The resolver must implement:

```php
IvanBaric\Gallery\Contracts\TenantResolver
```

When tenancy is enabled, gallery queries and media access are scoped to the current tenant.

## Publishing Views

Publish views when a project needs to customize markup:

```bash
php artisan vendor:publish --tag=gallery-views
```

## License

MIT

