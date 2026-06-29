<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) config('gallery.tables.galleries', 'galleries');

        if (! Schema::hasTable($tableName)) {
            return;
        }

        if (! Schema::hasColumn($tableName, 'team_id')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('team_id')->nullable()->after('tenant_type')->index();
            });
        }

        if (Schema::hasColumn($tableName, 'tenant_id')) {
            DB::table($tableName)
                ->whereNull('team_id')
                ->update(['team_id' => DB::raw('tenant_id')]);

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if ($this->hasIndex($tableName, 'galleries_tenant_collection_index')) {
                    $table->dropIndex('galleries_tenant_collection_index');
                }

                if ($this->hasIndex($tableName, 'galleries_tenant_id_index')) {
                    $table->dropIndex('galleries_tenant_id_index');
                }

                $table->dropColumn('tenant_id');
            });
        }

        if (! $this->hasIndex($tableName, 'galleries_tenant_collection_index')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->index(['tenant_type', 'team_id', 'collection_name'], 'galleries_tenant_collection_index');
            });
        }
    }

    public function down(): void
    {
        // Intentionally no-op: gallery tenancy is standardized on team_id.
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $definition): bool => ($definition['name'] ?? null) === $index);
    }
};
