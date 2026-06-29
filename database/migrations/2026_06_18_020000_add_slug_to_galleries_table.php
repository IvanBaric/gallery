<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('gallery.tables.galleries', 'galleries');

        if (! Schema::hasTable($table)) {
            return;
        }

        if (! Schema::hasColumn($table, 'slug')) {
            Schema::table($table, function (Blueprint $table): void {
                $table->string('slug')->nullable()->after('uuid')->index();
            });
        }

        DB::table($table)
            ->orderBy('id')
            ->select(['id', 'title', 'uuid', 'team_id', 'tenant_uuid'])
            ->get()
            ->each(function (object $gallery) use ($table): void {
                $slug = $this->uniqueSlug(
                    $table,
                    Str::slug((string) ($gallery->title ?: 'galerija-'.$gallery->id)) ?: (string) $gallery->uuid,
                    (string) ($gallery->team_id ?? ''),
                    (string) ($gallery->tenant_uuid ?? ''),
                    (int) $gallery->id,
                );

                DB::table($table)
                    ->where('id', $gallery->id)
                    ->whereNull('slug')
                    ->update(['slug' => $slug]);
            });
    }

    public function down(): void
    {
        $table = (string) config('gallery.tables.galleries', 'galleries');

        if (Schema::hasTable($table) && Schema::hasColumn($table, 'slug')) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropIndex(['slug']);
                $table->dropColumn('slug');
            });
        }
    }

    private function uniqueSlug(string $table, string $baseSlug, string $teamId, string $tenantUuid, int $ignoreId): string
    {
        $slug = $baseSlug;
        $counter = 2;

        while ($this->slugExists($table, $slug, $teamId, $tenantUuid, $ignoreId)) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function slugExists(string $table, string $slug, string $teamId, string $tenantUuid, int $ignoreId): bool
    {
        return DB::table($table)
            ->where('slug', $slug)
            ->where('id', '!=', $ignoreId)
            ->where(function ($query) use ($teamId, $tenantUuid): void {
                if ($teamId !== '') {
                    $query->orWhere('team_id', $teamId);
                }

                if ($tenantUuid !== '') {
                    $query->orWhere('tenant_uuid', $tenantUuid);
                }
            })
            ->exists();
    }
};
