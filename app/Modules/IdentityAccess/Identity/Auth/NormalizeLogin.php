<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Identity\Auth;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class NormalizeLogin
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (array_diff(array_keys($request->all()), ['login', 'password', '_token']) !== []) {
            throw ValidationException::withMessages(['login' => trans('auth.failed')]);
        }
        $request->validate(['login' => ['required', 'string', 'max:190'], 'password' => ['required', 'string']]);
        $request->merge(['login' => Str::lower(trim($request->string('login')->toString()))]);

        return $next($request);
    }
}
