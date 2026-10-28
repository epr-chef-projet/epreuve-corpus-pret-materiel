<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Providers;

use App\Modules\Catalog\Equipment\Http\CatalogIssueMapper;
use App\Modules\Catalog\Equipment\Models\Equipment;
use App\Modules\Catalog\Equipment\Policies\EquipmentPolicy;
use App\Modules\Catalog\Equipment\UseCases\PrepareDemo\PrepareCatalogDemo;
use App\Modules\IdentityAccess\PublicApi\ResolveActor;
use App\Shared\Errors\IssueData;
use App\Shared\Errors\NextActionData;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        RateLimiter::for('catalog.operation.close', static function (Request $request): Limit {
            $actor = app(ResolveActor::class)->handle($request);

            return Limit::perMinute(20)->by(hash('sha256', $actor->workshopId.'|'.$actor->actorId))->response(
                /** @param array<string, int|string> $headers */
                static function (Request $request, array $headers): JsonResponse {
                    $correlation = $request->attributes->get('correlationId');
                    $issue = new IssueData('identity_access.rate_limited', [], is_string($correlation) ? $correlation : (string) Str::uuid7(), 'notApplied', new NextActionData('reload'));

                    return response()->json(['issue' => $issue], 429, $headers)->header('Cache-Control', 'private, no-store');
                });
        });
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
