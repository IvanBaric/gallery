<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use IvanBaric\Gallery\Http\Controllers\MediaController;

$adminMiddleware = array_values(array_filter(array_merge(
    (array) config('gallery.routes.middleware', ['web', 'auth', 'verified']),
    ['gallery.permission:view'],
)));

Route::middleware($adminMiddleware)
    ->prefix(config('gallery.routes.prefix', 'app'))
    ->name(config('gallery.routes.name', 'admin.galleries.'))
    ->group(function (): void {
        Route::livewire(config('gallery.routes.path', 'galleries'), 'gallery.index')->name('index');
        Route::livewire(config('gallery.routes.path', 'galleries').'/{uuid}/edit', 'gallery.edit')->name('edit');
    });

Route::middleware(config('gallery.routes.media_middleware', ['web', 'auth']))
    ->get(config('gallery.routes.media_path', 'gallery/media').'/{media:uuid}/{conversion?}', MediaController::class)
    ->name(config('gallery.routes.media_route_name', 'gallery.media.show'));
