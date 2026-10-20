<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Providers;

use App\Modules\IdentityAccess\Access\Http\AccessIssueMapper;
use App\Modules\IdentityAccess\Access\Http\AccessLoginResponse;
use App\Modules\IdentityAccess\Access\UseCases\AuthorizeSessionChannel\SessionChannel;
use App\Modules\IdentityAccess\Identity\Auth\IdentityUserProvider;
use App\Modules\IdentityAccess\Identity\Models\User;
use App\Modules\IdentityAccess\Identity\UseCases\PrepareAccounts\PrepareIdentityAccounts;
use App\Modules\IdentityAccess\PublicApi\Role;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Fortify;

final class IdentityAccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();
        $this->app->bind(LoginResponse::class, AccessLoginResponse::class);
        $this->app->afterResolving(Handler::class, function (Handler $handler): void {
            $mapper = $this->app->make(AccessIssueMapper::class);
            $handler->renderable($mapper->validation(...));
            $handler->respondUsing($mapper->respond(...));
        });
    }

    public function boot(): void
    {
        foreach (['fr', 'en'] as $locale) {
            Lang::addLines(require __DIR__.'/../Identity/Lang/'.$locale.'.php', $locale);
        }
        Broadcast::channel('epr.session.{token}', SessionChannel::class);
        Auth::provider('identity_access', function (): IdentityUserProvider {
            $provider = $this->app->make(IdentityUserProvider::class);
            $this->app->refresh('request', $provider, 'setRequest');

            return $provider;
        });
        foreach (['borrow', 'operate-loans', 'manage-catalog'] as $ability) {
            Gate::define($ability, static fn (User $user): bool => Role::from($user->role)->allows($ability));
        }
        if ($this->app->runningInConsole()) {
            $this->commands([PrepareIdentityAccounts::class]);
        }
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
    }
}
