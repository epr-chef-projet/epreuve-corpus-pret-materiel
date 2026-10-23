<?php

declare(strict_types=1);

namespace App\Infrastructure\Operations;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use stdClass;

final class OperationRepository
{
    private function guard(OperationScope $scope): void
    {
        $connection = DB::connection('pgsql');
        if ($connection->transactionLevel() < 1) {
            throw new LogicException('An operation requires the business SQL unit.');
        }
        $context = $connection->selectOne("SELECT current_setting('epr.workshop_id', true) AS workshop, current_setting('epr.actor_id', true) AS actor");
        if ($context === null || $context->workshop !== $scope->workshopId || $context->actor !== $scope->actorId) {
            throw new LogicException('Operation scope differs from the SQL unit.');
        }
    }

    public function acquire(OperationScope $scope, string $inputHash): ?CommandReceipt
    {
        $this->guard($scope);
        if (preg_match('/\A[0-9a-f]{64}\z/D', $inputHash) !== 1) {
            throw new LogicException('Invalid canonical input fingerprint.');
        }
        $digest = hash('sha256', json_encode([$scope->workshopId, $scope->actorId, $scope->operationType, $scope->operationKey], JSON_THROW_ON_ERROR));
        $first = (int) hexdec(substr($digest, 0, 8));
        $second = (int) hexdec(substr($digest, 8, 8));
        DB::connection('pgsql')->select('SELECT pg_advisory_xact_lock(?::integer, ?::integer)', [$first > 2147483647 ? $first - 4294967296 : $first, $second > 2147483647 ? $second - 4294967296 : $second]);
        $row = $this->find($scope);
        if ($row !== null && ! hash_equals($row->input_sha256, $inputHash)) {
            throw new IntentMismatch('The same operation key describes a different intention.');
        }

        return $row === null ? null : $this->receipt($row, true);
    }

    public function record(OperationScope $scope, string $inputHash, string $resourceId, int $version, string $recordedAt): CommandReceipt
    {
        $this->guard($scope);
        DB::connection('pgsql')->table('operation_receipts')->insert([
            'id' => (string) Str::uuid7(), 'workshop_id' => $scope->workshopId, 'actor_id' => $scope->actorId,
            'operation_type' => $scope->operationType, 'operation_key' => $scope->operationKey, 'input_sha256' => $inputHash,
            'resource_type' => 'equipment', 'resource_id' => $resourceId, 'equipment_id' => $resourceId,
            'result_version' => $version, 'recorded_at' => $recordedAt,
        ]);

        return new CommandReceipt($scope->operationKey, $scope->operationType, 'equipment', $resourceId, $version, $recordedAt, false);
    }

    public function read(OperationScope $scope): ?CommandReceipt
    {
        $this->guard($scope);
        $row = $this->find($scope);

        return $row === null ? null : $this->receipt($row, true);
    }

    private function find(OperationScope $scope): ?stdClass
    {
        return DB::connection('pgsql')->table('operation_receipts')
            ->where('workshop_id', $scope->workshopId)->where('actor_id', $scope->actorId)
            ->where('operation_type', $scope->operationType)->where('operation_key', $scope->operationKey)
            ->first(['operation_key', 'operation_type', 'input_sha256', 'resource_type', 'resource_id', 'result_version', 'recorded_at']);
    }

    private function receipt(stdClass $row, bool $replayed): CommandReceipt
    {
        return new CommandReceipt($row->operation_key, $row->operation_type, $row->resource_type, $row->resource_id, (int) $row->result_version, CarbonImmutable::parse($row->recorded_at)->utc()->format('Y-m-d\TH:i:s.v\Z'), $replayed);
    }
}
