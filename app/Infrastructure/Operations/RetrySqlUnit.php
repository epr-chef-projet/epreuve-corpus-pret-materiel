<?php

declare(strict_types=1);

namespace App\Infrastructure\Operations;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;

final class RetrySqlUnit
{
    /** @param Closure(int): void $work Receives the remaining time budget in milliseconds. */
    public function run(Closure $work): void
    {
        $connection = DB::connection('pgsql');
        $outer = $connection->transactionLevel() > 0;
        $deadline = hrtime(true) + 15_000_000_000;
        for ($attempt = 1; ; $attempt++) {
            $remaining = max(1, (int) (($deadline - hrtime(true)) / 1_000_000));
            try {
                $work($remaining);

                return;
            } catch (QueryException $error) {
                $sqlState = $error->errorInfo[0] ?? null;
                if ($outer || $attempt >= 3 || ! in_array($sqlState, ['40P01', '40001'], true) || $connection->transactionLevel() !== 0 || $connection->getPdo()->inTransaction() || hrtime(true) + 100_000_000 >= $deadline) {
                    throw $error;
                }
                usleep($attempt * 50_000);
            }
        }
    }

    public function limit(int $milliseconds): void
    {
        $connection = DB::connection('pgsql');
        if ($connection->transactionLevel() < 1) {
            throw new LogicException('SQL budget requires an active unit.');
        }
        $connection->select("SELECT set_config('transaction_timeout', ?, true), set_config('statement_timeout', ?, true)", [$milliseconds.'ms', min(5000, $milliseconds).'ms']);
    }
}
