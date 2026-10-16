<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Providers;

use App\Modules\IdentityAccess\Accounts\Auth\NormalizeLogin;
use App\Modules\IdentityAccess\Sessions\UseCases\StartSession\StartSessionAction;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::loginView(fn () => Inertia::render('auth/Login'));
        Fortify::authenticateThrough(fn (): array => [NormalizeLogin::class, AttemptToAuthenticate::class, PrepareAuthenticatedSession::class, StartSessionAction::class]);
        RateLimiter::for('login', static function (Request $request): Limit {
            $login = $request->input('login');
            $key = is_string($login) ? Str::lower(trim($login)) : '';

            return Limit::perMinute(5)->by(hash('sha256', $key.'|'.$request->ip()));
        });
    }
}
