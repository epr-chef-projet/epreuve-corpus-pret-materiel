<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        throw new RuntimeException('Use identity-access:prepare-accounts with its explicit local target; global seeding is not supported.');
    }
}
