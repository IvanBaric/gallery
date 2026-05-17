<?php

declare(strict_types=1);

namespace IvanBaric\Gallery;

use Illuminate\Support\ServiceProvider;
use IvanBaric\Gallery\Console\Commands\MigrateModelMediaCommand;
use IvanBaric\Gallery\Contracts\TenantResolver;
use IvanBaric\Gallery\Http\Livewire\GalleryEdit;
use IvanBaric\Gallery\Http\Livewire\GalleryIndex;
use IvanBaric\Gallery\Http\Livewire\GalleryManager;
use IvanBaric\Gallery\Http\Livewire\StandaloneGallerySelector;
use IvanBaric\Gallery\Http\Middleware\EnsureGalleryPermission;
use Livewire\Livewire;

class GalleryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/gallery.php', 'gallery');

        $this->app->bind(TenantResolver::class, function ($app): TenantResolver {
            $resolver = (string) $app['config']->get('gallery.tenancy.resolver');

            return $app->make($resolver);
        });

        if ((bool) config('gallery.media.register_media_model', true)) {
            $this->app['config']->set('media-library.media_model', config('gallery.models.media'));
        }
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'gallery');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->app['router']->aliasMiddleware('gallery.permission', EnsureGalleryPermission::class);

        Livewire::component('gallery.manager', GalleryManager::class);
        Livewire::component('gallery.index', GalleryIndex::class);
        Livewire::component('gallery.edit', GalleryEdit::class);
        Livewire::component('gallery.standalone-selector', StandaloneGallerySelector::class);

        if ((bool) config('gallery.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                MigrateModelMediaCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/gallery.php' => config_path('gallery.php'),
            ], 'gallery-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/gallery'),
            ], 'gallery-views');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'gallery-migrations');
        }
    }
}
