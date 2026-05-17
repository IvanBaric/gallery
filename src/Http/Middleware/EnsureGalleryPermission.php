<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use IvanBaric\Gallery\Support\GalleryPermissions;
use Symfony\Component\HttpFoundation\Response;

final class EnsureGalleryPermission
{
    public function handle(Request $request, Closure $next, string $action): Response
    {
        GalleryPermissions::authorize($action);

        return $next($request);
    }
}
