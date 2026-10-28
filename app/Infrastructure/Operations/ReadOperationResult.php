<?php

declare(strict_types=1);

namespace App\Infrastructure\Operations;

/** The owner establishes the current authorized SQL scope before looking up its command. */
final class ReadOperationResult
{
    public function __construct(private OperationRepository $operations) {}

    public function handle(OperationScope $scope): OperationResultData|ClosedOperationResultData
    {
        $receipt = $this->operations->read($scope);

        if ($receipt instanceof OperationClosureData) {
            return new ClosedOperationResultData($receipt);
        }

        return new OperationResultData($receipt === null ? 'notEstablished' : 'applied', $receipt);
    }
}
