<?php

declare(strict_types=1);

namespace Tests\Fixtures\IdentityAccess;

use RuntimeException;
use Tests\TestCase;

final class Accounts
{
    /** @return array{password: string, users: list<array{id: string, workshopId: string, login: string, role: string, displayName: string}>} */
    public static function receipt(): array
    {
        $path = base_path('.epr-local/identity-testing.json');
        if (! is_file($path)) {
            throw new RuntimeException('Prepare the eight testing accounts explicitly with the migrator before running access tests.');
        }

        return json_decode(file_get_contents($path) ?: '', true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array{id: string, workshopId: string, login: string, role: string, displayName: string} */
    public static function user(string $login = 'atelier-1-borrower-1'): array
    {
        foreach (self::receipt()['users'] as $user) {
            if ($user['login'] === $login) {
                return $user;
            }
        }
        throw new RuntimeException('Unknown synthetic account.');
    }

    public static function signIn(TestCase $test, string $login = 'atelier-1-borrower-1'): void
    {
        $test->post('/login', ['login' => $login, 'password' => self::receipt()['password']])->assertRedirect('/dashboard');
        $test->withCookie(config('session.cookie'), session()->getId());
    }
}
