<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Access\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse;

final class AccessLoginResponse implements LoginResponse
{
    /** @param Request $request */
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : redirect()->route($request->user()?->role === 'borrower' ? 'catalogue' : 'teamRequests');
    }
}
