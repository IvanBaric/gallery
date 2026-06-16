<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use IvanBaric\Corexis\Contracts\Events\DomainEvent;
use IvanBaric\Gallery\Models\Gallery;

final readonly class GalleryMediaDeleted implements DomainEvent, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<int, int>  $mediaIds
     */
    public function __construct(
        public Gallery $gallery,
        public array $mediaIds,
    ) {}
}
