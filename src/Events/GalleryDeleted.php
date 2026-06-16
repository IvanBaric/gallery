<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use IvanBaric\Corexis\Contracts\Events\DomainEvent;

final readonly class GalleryDeleted implements DomainEvent, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int|string $galleryId,
        public ?string $uuid,
        public string $collection,
    ) {}
}
