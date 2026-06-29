<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GallerySetting extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $setting): void {
            if (Schema::hasColumn($setting->getTable(), 'uuid') && blank($setting->uuid)) {
                $setting->uuid = (string) Str::uuid();
            }
        });
    }

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
