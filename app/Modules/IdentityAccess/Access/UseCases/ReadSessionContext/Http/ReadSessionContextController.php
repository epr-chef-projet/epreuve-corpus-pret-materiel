<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Access\UseCases\ReadSessionContext\Http;

use App\Modules\IdentityAccess\Access\Data\SessionContextData;
use App\Modules\IdentityAccess\Identity\Data\AuthenticatedUserData;
use App\Modules\IdentityAccess\Identity\Models\User;
use App\Modules\IdentityAccess\PublicApi\ResolveActor;
use App\Modules\IdentityAccess\PublicApi\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class ReadSessionContextController
{
    public function __invoke(Request $request, ResolveActor $actors): JsonResponse
    {
        $user = $request->user();
        $data = new SessionContextData(null, null, null, null, app()->getLocale());
        if ($user instanceof User) {
            $actor = $actors->handle($request);
            $data = new SessionContextData(
                new AuthenticatedUserData($actor->actorId, $user->login, $user->display_name, $actor->workshopId, $user->workshop_name, Role::from($actor->role)),
                $request->session()->get('identity_access.broadcast_token'),
                $request->session()->get('identity_access.auth_generation'),
                Carbon::createFromTimestampUTC($request->session()->get('identity_access.expires_at'))->format('Y-m-d\TH:i:s.v\Z'),
                app()->getLocale(),
            );
        }

        return response()->json($data->toArray())->header('Cache-Control', 'private, no-store');
    }
}
