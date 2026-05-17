<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Models;

use Illuminate\Database\Eloquent\Model;

class GallerySetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function getTable(): string
    {
        return (string) config('gallery.tables.settings', 'gallery_settings');
    }
}
