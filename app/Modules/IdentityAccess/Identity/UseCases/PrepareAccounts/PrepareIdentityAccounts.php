<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Identity\UseCases\PrepareAccounts;

use Illuminate\Console\Command;

final class PrepareIdentityAccounts extends Command
{
    protected $signature = 'identity-access:prepare-accounts {--target= : Explicit local database: laravel or testing}';

    protected $description = 'Prepare the eight synthetic accounts once, preserving existing data and writing a private receipt';

    public function handle(PrepareAccountsAction $prepare): int
    {
        $path = $prepare->handle((string) $this->option('target'));
        $this->info('Accounts prepared. Private receipt: '.$path);

        return self::SUCCESS;
    }
}
