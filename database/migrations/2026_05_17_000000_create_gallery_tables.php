<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $galleriesTable = (string) config('gallery.tables.galleries', 'galleries');
        $settingsTable = (string) config('gallery.tables.settings', 'gallery_settings');

        if (! Schema::hasTable($galleriesTable)) {
            Schema::create($galleriesTable, function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->nullableMorphs('galleryable');
                $table->string('collection_name')->default('images')->index();
                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->unsignedBigInteger('featured_media_id')->nullable()->index();
                $table->string('tenant_type')->nullable()->index();
                $table->string('team_id')->nullable()->index();
                $table->uuid('tenant_uuid')->nullable()->index();
                $table->json('custom_properties')->nullable();
                $table->unsignedInteger('lock_version')->default(0);
                $table->timestamps();

                $table->unique(['galleryable_type', 'galleryable_id', 'collection_name'], 'galleries_owner_collection_unique');
                $table->index(['tenant_type', 'team_id', 'collection_name'], 'galleries_tenant_collection_index');
            });
        }

        if (! Schema::hasTable($settingsTable)) {
            Schema::create($settingsTable, function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('key')->unique();
                $table->json('value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists((string) config('gallery.tables.settings', 'gallery_settings'));
        Schema::dropIfExists((string) config('gallery.tables.galleries', 'galleries'));
    }
};
