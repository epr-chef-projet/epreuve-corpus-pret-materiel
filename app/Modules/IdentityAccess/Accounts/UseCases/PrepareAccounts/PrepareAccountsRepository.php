<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Accounts\UseCases\PrepareAccounts;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PrepareAccountsRepository
{
    public function assertTarget(string $target): void
    {
        $identity = DB::selectOne('SELECT session_user, current_user, current_database() AS database, current_schema() AS schema');
        if (! app()->environment('local') || app()->configurationIsCached() || ! in_array($target, ['laravel', 'testing'], true) || $identity === null || $identity->session_user !== 'epr_migrator' || $identity->current_user !== 'epr_owner' || $identity->database !== $target || $identity->schema !== 'epr') {
            throw new RuntimeException('Account preparation requires the isolated migrator and an explicit local target.');
        }
    }

    public function isEmpty(): bool
    {
        return ! DB::table('identity_access_users')->exists() && ! DB::table('identity_access_workshops')->exists();
    }

    public function createWorkshop(string $id, string $name): void
    {
        DB::table('identity_access_workshops')->insert(['id' => $id, 'name' => $name]);
    }

    public function createUser(string $id, string $workshopId, string $login, string $displayName, string $role, string $password): void
    {
        DB::table('identity_access_users')->insert(['id' => $id, 'workshop_id' => $workshopId, 'login' => $login, 'display_name' => $displayName, 'role' => $role, 'password' => $password]);
    }
}
