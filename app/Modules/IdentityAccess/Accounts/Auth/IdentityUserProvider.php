<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Accounts\Auth;

use App\Modules\IdentityAccess\Accounts\Models\User;
use App\Modules\IdentityAccess\Accounts\UseCases\Authenticate\IdentityRepository;
use App\Modules\IdentityAccess\Sessions\UseCases\ManageSession\SessionRepository;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class IdentityUserProvider implements UserProvider
{
    public function __construct(private IdentityRepository $identities, private SessionRepository $sessions, private Request $request) {}

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    public function retrieveById(mixed $identifier): ?Authenticatable
    {
        if (! is_string($identifier) || ! Str::isUuid($identifier) || ! $this->request->hasSession()) {
            return null;
        }

        return $this->identities->resolve($identifier, $this->sessions->key($this->request->session()->getId()));
    }

    public function retrieveByToken(mixed $identifier, mixed $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, mixed $token): void {}

    /** @param array<string, mixed> $credentials */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $login = $credentials['login'] ?? null;

        return is_string($login) ? $this->identities->findByLogin(Str::lower(trim($login))) : null;
    }

    /** @param array<string, mixed> $credentials */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return isset($credentials['password']) && is_string($credentials['password']) && Hash::check($credentials['password'], $user->getAuthPassword());
    }

    /** @param array<string, mixed> $credentials */
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        if ($user instanceof User && isset($credentials['password']) && is_string($credentials['password']) && ($force || Hash::needsRehash($user->getAuthPassword()))) {
            $this->identities->rehash($user, Hash::make($credentials['password']));
        }
    }
}
