<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Providers;

use App\Modules\Catalog\Equipment\Http\CatalogIssueMapper;
use App\Modules\Catalog\Equipment\Models\Equipment;
use App\Modules\Catalog\Equipment\Policies\EquipmentPolicy;
use App\Modules\Catalog\Equipment\UseCases\PrepareDemo\PrepareCatalogDemo;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\ServiceProvider;

final class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->afterResolving(Handler::class, function (Handler $handler): void {
            $mapper = $this->app->make(CatalogIssueMapper::class);
            $handler->renderable($mapper->render(...));
        });
    }

    public function boot(): void
    {
        Gate::policy(Equipment::class, EquipmentPolicy::class);
        if ($this->app->runningInConsole()) {
            $this->commands([PrepareCatalogDemo::class]);
        }
        foreach (['fr', 'en'] as $locale) {
            Lang::addLines(require __DIR__.'/../Equipment/Lang/'.$locale.'.php', $locale);
        }
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
    }
}
