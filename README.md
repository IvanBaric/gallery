# Gallery

Reusable Laravel gallery package built on top of Spatie Media Library, Livewire, Flux UI and `ivanbaric/admin-ui`.

The package is intentionally generic. It can be used for any model or project that needs galleries, image uploads, image metadata, generated conversions, admin management and a polished public lightbox.

## What The Package Contains

- Gallery model with optional owner relation to any Eloquent model.
- Custom Media model with tenant-aware access helpers and alt text helper.
- `HasGalleries` trait for attaching galleries to existing models.
- Action classes for gallery, media and standalone assignment write workflows.
- Domain events for successful gallery and media state changes.
- Livewire Form Objects for gallery metadata and media metadata forms.
- Livewire admin manager component for uploads, ordering, featured image, SEO metadata and deletion.
- Admin gallery index and edit screens.
- Standalone gallery selector for assigning empty standalone galleries to any model with `flux:select`.
- Image size settings with WordPress-like defaults.
- Queued or synchronous conversion regeneration.
- Protected media route.
- Optional tenancy support.
- Optional permission layer for view, create, update, upload, attach, SEO, regenerate, settings and delete actions.
- Public `<x-gallery::lightbox>` component with thumbnails, keyboard navigation, swipe gestures, counter, modal view and empty state.

## Requirements

- PHP 8.2+
- Laravel 11, 12 or 13
- Livewire
- Flux UI
- Spatie Media Library 11
- `ivanbaric/admin-ui` for the admin screens
- `ivanbaric/corexis` for `ActionResult` and domain event contracts

## Architecture

Write workflows follow the ecosystem package standard:

```text
Livewire Component -> Livewire Form Object -> Action -> Corexis ActionResult -> Domain Event -> Listener
```

The package keeps Spatie Media Library integration inside `gallery`, but it does not know about pages, blog posts, products or other content packages. Other packages should react to gallery events through listeners instead of being called directly from gallery actions.

Current Actions:

- `CreateGalleryAction`
- `UpdateGalleryAction`
- `DeleteGalleryAction`
- `UploadGalleryMediaAction`
- `DeleteGalleryMediaAction`
- `ReorderGalleryMediaAction`
- `SetFeaturedGalleryMediaAction`
- `UpdateGalleryMediaMetaAction`
- `AttachGalleryToModelAction`
- `DetachGalleryFromModelAction`

Current Events:

- `GalleryCreated`
- `GalleryUpdated`
- `GalleryDeleted`
- `GalleryMediaUploaded`
- `GalleryMediaDeleted`
- `GalleryMediaReordered`
- `GalleryMediaFeatured`
- `GalleryMediaMetaUpdated`
- `GalleryAttachedToModel`
- `GalleryDetachedFromModel`

Livewire components coordinate UI state, Flux modals, toasts and redirects. Business writes belong in Actions and return `IvanBaric\Corexis\Data\ActionResult`.

## Installation

```bash
composer require ivanbaric/gallery
php artisan migrate
```

Laravel auto-discovers the service provider.

Publish the config when the application needs custom routes, validation, conversions, tenancy or permissions:

```bash
php artisan vendor:publish --tag=gallery-config
```

Publish views only when you want to override package markup:

```bash
php artisan vendor:publish --tag=gallery-views
```

## Tailwind

Add package views to Tailwind sources:

```css
@source '../../vendor/ivanbaric/gallery/resources/views/**/*.blade.php';
```

For a local path repository:

```css
@source '../../packages/ivanbaric/gallery/resources/views/**/*.blade.php';
```

## Model Setup

Add the trait to any model that owns galleries:

```php
use IvanBaric\Gallery\Concerns\HasGalleries;

class Product extends Model implements HasMedia
{
    use HasGalleries;
}
```

The trait provides:

- `galleries()`
- `gallery($collection = 'images')`
- `getOrCreateGallery($collection = 'images')`
- `galleryMedia($collection = 'images')`
- `galleryMediaCount($collection = 'images')`
- `galleryFeaturedMedia($collection = 'images')`
- `galleryImageUrl($collection = 'images', $conversion = 'large')`
- `migrateMediaCollectionToGallery($collection = 'images')`

## Admin Manager

Render the reusable admin manager anywhere:

```blade
<x-gallery::manager
    :model="$model"
    collection="images"
    context="default"
    :title="__('Fotografije')"
    :description="__('Dodajte slike, uredite SEO podatke, promijenite redoslijed i istaknutu sliku.')"
/>
```

The manager supports:

- Multiple image uploads with configurable validation.
- Drag-and-drop ordering.
- Featured image selection.
- Bulk selection, bulk deletion and selected-image regeneration.
- SEO metadata per image: alt text, title, caption, description, credit, source URL, license and decorative image flag.
- Delete confirmation modals.
- Gallery conversion regeneration.

For models that use `HasGalleries`, the manager is lazy. Opening a form does not create an empty attached gallery. The gallery row is created only when the first image is uploaded.

When the last image is removed from an attached model gallery, the empty gallery row is deleted automatically. If the user uploads an image again later, the manager creates a fresh gallery for that model and collection.

## Standalone Gallery Assignment

Standalone galleries are useful as reusable drafts. You can create an empty gallery in the gallery module, then later assign it to a model such as a post, page, product or vehicle.

The package ships a ready-made Flux selector:

```blade
<x-gallery::standalone-selector
    :model="$post"
    collection="images"
    :description="__('Odaberite praznu samostalnu galeriju koju želite dodijeliti ovom zapisu.')"
/>
```

By default the selector lists only empty standalone galleries for the selected collection. When the user clicks the button, the selected gallery is assigned through `AttachGalleryToModelAction`.

Selector options:

- `model`: saved Eloquent model that uses `IvanBaric\Gallery\Concerns\HasGalleries`.
- `collection`: target collection, default `images`.
- `emptyOnly`: list only empty standalone galleries, default `true`.
- `allowReplace`: allow replacing an existing gallery for that collection, default `false`.
- `showCurrent`: show the currently assigned gallery, default `true`.
- `label`, `placeholder`, `buttonLabel`, `description`: UI text overrides.

You can also use the backend action directly in your own Livewire form:

```php
use IvanBaric\Gallery\Actions\AttachGalleryToModelAction;
use IvanBaric\Gallery\Models\Gallery;

$gallery = Gallery::query()
    ->standalone()
    ->empty()
    ->where('uuid', $this->galleryUuid)
    ->firstOrFail();

$result = app(AttachGalleryToModelAction::class)->handle($post, $gallery, collection: 'images');
```

If the model already has a gallery for the collection, `attachStandaloneGallery()` refuses to replace it unless you pass `replace: true`:

```php
$post->attachStandaloneGallery($gallery, collection: 'images', replace: true);
```

Replacement detaches the previous gallery and leaves it as standalone; it does not delete its media.

## Public Lightbox

Use the lightbox on any public or private page:

```blade
<x-gallery::lightbox
    :media="$model->galleryMedia('images')"
    :title="$model->title"
    :featured="$model->is_featured ?? false"
    :fallback-alt="$model->title"
/>
```

You can also pass a prepared array:

```blade
<x-gallery::lightbox
    :images="[
        ['thumb' => $thumbUrl, 'main' => $mainUrl, 'lightbox' => $fullUrl, 'alt' => 'Opis slike'],
    ]"
    title="Galerija"
/>
```

Main options:

- `media` or `images`
- `title`
- `featured` and `featuredLabel`
- `fallbackAlt`
- `mainConversion`, `lightboxConversion`, `thumbnailConversion`
- `showFeaturedBadge`
- `showZoomHint`
- `showCounter`
- `showThumbnails`
- `showLightboxTitle`
- `showEmptyState`
- `emptyTitle` and `emptyDescription`
- `openLabel`, `closeLabel`, `previousLabel`, `nextLabel`, `thumbnailLabel`
- `scrollPreviousLabel`, `scrollNextLabel`, `dialogLabel`, `zoomLabel`
- `aspect`, `mainImageClass`, `thumbnailImageClass`

The component includes the full interaction set: main image preview, hover zoom hint, modal lightbox, close button, previous/next arrows, keyboard navigation, swipe navigation, counter, thumbnail strip and scroll controls.

## Admin Routes

By default the package registers:

```text
GET /app/galleries
GET /app/galleries/{uuid}/edit
```

Route names:

```text
admin.galleries.index
admin.galleries.edit
```

You can change route prefix, path, name and middleware in `config/gallery.php`.

## Image Sizes

Default conversions:

- `thumbnail` 150x150 crop
- `thumb` 600x400 crop
- `medium` 300x300 contain
- `medium_large` 768px wide
- `large` 1024x1024 contain
- `xlarge` 1536x1536 contain
- `admin_thumb` 600x400 crop

The admin settings screen can change labels, dimensions, fit mode and enabled state. After changing sizes, regenerate existing images.

## Regeneration

Regeneration can be started from the admin UI or through Spatie:

```bash
php artisan media-library:regenerate "IvanBaric\\Gallery\\Models\\Gallery"
```

The package also includes a queued job for regenerating a whole gallery or selected media.

## Permissions

Permissions are optional so the package can be dropped into simple projects without access-control setup.

Enable enforcement:

```env
GALLERY_PERMISSIONS_ENABLED=true
```

Default permission codes:

```php
gallery.view
gallery.create
gallery.update
gallery.upload
gallery.attach
gallery.seo
gallery.regenerate
gallery.settings
gallery.delete
```

For `ivanbaric/velora`, register the permission group in the host application's `config/velora.php`:

```php
use IvanBaric\Gallery\Support\GalleryPermissions;

'permissions' => [
    // existing groups...
    ...GalleryPermissions::groups(),
],
```

Assign permissions to roles:

```php
'permissions' => [
    ...array_values(GalleryPermissions::defaults()),
],
```

Or assign only selected actions:

```php
'permissions' => [
    GalleryPermissions::VIEW,
    GalleryPermissions::ATTACH,
    GalleryPermissions::UPLOAD,
    GalleryPermissions::SEO,
],
```

When enabled, the package checks permissions on:

- Admin gallery routes through `gallery.permission:view`.
- Livewire actions such as create, upload, attach, update, SEO edit, regenerate, settings save and delete.
- Visible admin controls, so users only see actions they can use.

## Delete Confirmation

Gallery deletion uses a confirmation modal. By default, the package asks for the current user's password only when the gallery contains media. Empty galleries can be deleted from the confirmation modal without typing a password.

Configure the behavior in `config/gallery.php`:

```php
'deletion' => [
    'password_confirmation' => 'non_empty',
],
```

Available modes:

- `non_empty`: require a password only when the gallery has media.
- `always`: require a password for every gallery deletion.
- `never`: never ask for a password in the delete modal.

The same setting can be controlled with:

```env
GALLERY_DELETE_PASSWORD_CONFIRMATION=non_empty
```

You can override action codes in `config/gallery.php`:

```php
'permissions' => [
    'enabled' => true,
    'actions' => [
        'view' => 'media.view',
        'upload' => 'media.upload',
    ],
],
```

## Validation

Default upload validation:

- Maximum files per gallery: 30
- Maximum file size: 3 MB
- Extensions: JPG, JPEG, PNG, WEBP

Override globally in `validation`, or per use case in `contexts`.

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

When enabled, gallery queries and protected media access are scoped to the current tenant.

## Migrating Existing Media

If an application already stores images directly on a model, migrate them into galleries:

```bash
php artisan gallery:migrate-model-media "App\\Models\\Product" images
```

The command moves media rows to the package gallery model while preserving files.

## License

MIT
