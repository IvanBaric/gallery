<?php

declare(strict_types=1);

namespace IvanBaric\Gallery\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use IvanBaric\Gallery\Concerns\HasGalleries;

final class MigrateModelMediaCommand extends Command
{
    protected $signature = 'gallery:migrate-model-media {modelType} {collection=images} {--chunk=100}';

    protected $description = 'Move existing Spatie media from an application model to package Gallery records.';

    public function handle(): int
    {
        $modelType = (string) $this->argument('modelType');
        $collection = (string) $this->argument('collection');
        $chunk = max(1, (int) $this->option('chunk'));

        if (! class_exists($modelType) || ! is_subclass_of($modelType, Model::class)) {
            $this->error("Model [$modelType] does not exist.");

            return self::FAILURE;
        }

        if (! in_array(HasGalleries::class, class_uses_recursive($modelType), true)) {
            $this->error("Model [$modelType] must use ".HasGalleries::class.'.');

            return self::FAILURE;
        }

        $migrated = 0;

        $modelType::query()
            ->with('media')
            ->chunkById($chunk, function ($models) use ($collection, &$migrated): void {
                foreach ($models as $model) {
                    $migrated += $model->migrateMediaCollectionToGallery($collection);
                }
            });

        $this->info("Migrated $migrated media item(s).");

        return self::SUCCESS;
    }
}
