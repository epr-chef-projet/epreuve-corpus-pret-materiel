<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Providers;

use App\Modules\IdentityAccess\Accounts\Auth\IdentityUserProvider;
use App\Modules\IdentityAccess\Accounts\Models\User;
use App\Modules\IdentityAccess\Accounts\UseCases\PrepareAccounts\PrepareIdentityAccounts;
use App\Modules\IdentityAccess\PublicApi\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

final class IdentityAccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
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
