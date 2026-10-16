<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Accounts\UseCases\PrepareAccounts;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

final class PrepareAccountsAction
{
    public function __construct(private PrepareAccountsRepository $accounts) {}

    public function handle(string $target): string
    {
        $this->accounts->assertTarget($target);
        $path = base_path('.epr-local/identity-'.$target.'.json');
        if (! $this->accounts->isEmpty()) {
            throw new RuntimeException('Existing accounts preserved. Reuse their private receipt; no automatic reseeding.');
        }
        if (is_file($path) || is_link($path)) {
            throw new RuntimeException('Existing private account receipt preserved for inspection.');
        }
        $password = bin2hex(random_bytes(32));
        $hash = Hash::make($password);
        $users = [];
        $workshops = [];
        for ($number = 1; $number <= 2; $number++) {
            $workshop = (string) Str::uuid7();
            $workshops[$workshop] = 'Atelier '.$number;
            foreach (['manager', 'staff', 'borrower-1', 'borrower-2'] as $suffix) {
                $role = str_starts_with($suffix, 'borrower') ? 'borrower' : $suffix;
                $users[] = ['id' => (string) Str::uuid7(), 'workshopId' => $workshop, 'login' => 'atelier-'.$number.'-'.$suffix, 'role' => $role, 'displayName' => 'Compte '.$number.' '.$suffix];
            }
        }
        $content = json_encode(['target' => $target, 'password' => $password, 'users' => $users], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
        $handle = fopen($path, 'x');
        if ($handle === false) {
            throw new RuntimeException('Could not create private account receipt.');
        }
        chmod($path, 0600);
        try {
            if (fwrite($handle, $content) !== strlen($content) || ! fflush($handle) || ! fsync($handle)) {
                throw new RuntimeException('Incomplete private account receipt. Preserve it for recovery.');
            }
        } finally {
            fclose($handle);
        }
        DB::transaction(function () use ($workshops, $users, $hash): void {
            if (! $this->accounts->isEmpty()) {
                throw new RuntimeException('Concurrent preparation detected. Preserve the receipt for inspection.');
            }
            foreach ($workshops as $id => $name) {
                $this->accounts->createWorkshop($id, $name);
            }
            foreach ($users as $user) {
                $this->accounts->createUser($user['id'], $user['workshopId'], $user['login'], $user['displayName'], $user['role'], $hash);
            }
        });

        return $path;
    }
}
