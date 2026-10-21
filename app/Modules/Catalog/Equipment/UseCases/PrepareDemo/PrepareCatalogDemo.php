<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Equipment\UseCases\PrepareDemo;

use App\Modules\Catalog\Database\Seeders\CatalogDemoSeeder;
use Illuminate\Console\Command;

final class PrepareCatalogDemo extends Command
{
    protected $signature = 'catalog:prepare-demo {--target= : Explicit empty local Catalog database: laravel or testing}';

    protected $description = 'Prepare six synthetic equipment references per workshop without overwriting existing equipment';

    public function handle(CatalogDemoSeeder $demo): int
    {
        $path = $demo->prepare((string) $this->option('target'));
        $this->info('Catalog demo prepared. Private fixture manifest: '.$path);

        return self::SUCCESS;
    }
}
