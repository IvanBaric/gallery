<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Http\Controllers;

use Illuminate\Http\Request;
use IvanBaric\Gallery\Models\Media;

final class MediaController
{
    public function __invoke(Request $request, Media $media, ?string $conversion = null)
    {
        abort_unless($media->isAccessibleForCurrentTenant(), 403);

        return $media->toInlineResponse($request, $conversion ?: '');
    }
}
