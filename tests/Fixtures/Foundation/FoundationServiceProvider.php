<?php

declare(strict_types=1);

namespace Tests\Fixtures\Foundation;

use App\Modules\IdentityAccess\Identity\Models\User;
use App\Modules\IdentityAccess\PublicApi\Role;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Vite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class FoundationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $identity = DB::selectOne('SELECT session_user, current_user, current_database() AS database, current_schema() AS schema');
        if (! $this->app->environment('testing') || $this->app->configurationIsCached() || $identity === null || $identity->session_user !== 'epr_app' || $identity->current_user !== 'epr_app' || $identity->database !== 'testing' || $identity->schema !== 'epr' || $this->app->storagePath() !== $this->app->basePath('storage/framework/browser')) {
            throw new \RuntimeException('Browser fixtures require their test database and storage.');
        }
        $this->app->make(Vite::class)->useHotFile(storage_path('hot'))->useBuildDirectory('browser-build');
        view()->addNamespace('test-fixtures', base_path('tests/Fixtures'));

        Broadcast::channel('foundation.probe.{id}', static fn (User $user, string $id): bool => (string) $user->id === $id);
        Route::middleware(['web', 'auth'])->group(function (): void {
            Route::get('/__foundation', static function (Request $request): Response {
                $user = $request->user();
                abort_unless($user instanceof User, 403);

                return Inertia::render('_foundation/Probe', ['probeUserId' => $user->id, 'csrfToken' => csrf_token()])->rootView('test-fixtures::foundation');
            });
            Route::get('/__foundation/session', static fn (): Response => Inertia::render('_foundation/SessionProbe', ['csrfToken' => csrf_token()])->rootView('test-fixtures::foundation'));
            Route::post('/__foundation/publish', static function (Request $request): JsonResponse {
                $user = $request->user();
                abort_unless($user instanceof User, 403);
                event(new ProbePublished($user->id, 'server-marker'));

                return response()->json(['published' => true]);
            });
            Route::post('/__foundation/session-publish', static function (Request $request): JsonResponse {
                $user = $request->user();
                abort_unless($user instanceof User, 403);
                $id = (string) Str::uuid7();
                SessionProbeJob::dispatch($user->workshop_id, $user->id, Role::from($user->role), $id, $request->boolean('barrier'));

                return response()->json(['eventId' => $id], 202);
            })->name('fixture.sessionPublish');
            Route::post('/__foundation/end-session', static function (Request $request): HttpResponse {
                $reason = $request->input('reason');
                abort_unless(in_array($reason, ['expired', 'missing'], true), 422);
                if ($reason === 'expired') {
                    $request->session()->put('identity_access.expires_at', now()->subSecond()->getTimestamp());
                } else {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }

                return response()->noContent();
            });
        });
        Route::middleware('web')->prefix('/__foundation/session-probe/{id}')->whereUuid('id')->group(function (): void {
            Route::get('/', static function (string $id): JsonResponse {
                $path = 'foundation/session-probe/'.$id;
                $disk = Storage::disk('local');

                return response()->json(['started' => $disk->exists($path.'.started'), 'result' => $disk->exists($path.'.done') ? json_decode((string) $disk->get($path.'.done'), true, flags: JSON_THROW_ON_ERROR) : null]);
            });
            Route::post('/release', static function (string $id): HttpResponse {
                abort_unless(Storage::disk('local')->exists('foundation/session-probe/'.$id.'.started'), 404);
                Storage::disk('local')->put('foundation/session-probe/'.$id.'.release', 'release');

                return response()->noContent();
            });
        });
        $this->app->afterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->call(static function (): void {
                if (Storage::disk('local')->put('foundation/scheduler.txt', 'scheduler-observed') !== true) {
                    throw new \RuntimeException('Scheduler fixture write failed.');
                }
            })
                ->name('foundation-scheduler')->everyMinute()->withoutOverlapping();
        });
    }
}
