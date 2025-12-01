<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\Internal\UpdateWhenThenDefinitionsBuilder;
use Doctrine\DBAL\ParameterType;

class UpdateWhenThenTransactionFactory
{
    public function __construct(
        private readonly UpdateWhenThenDefinitionsBuilder $updateWhenThenDefinitionsBuilder
    ) {
    }

    public function factory(
        string $tableName,
        array $rows,
        string $identifierColumn,
        int|ParameterType $identifierColumnType,
        array $updateColumns,
        array $updateColumnTypes = [],
        bool $isIdempotent = true,
    ): UpdateWhenThenTransaction {
        return new UpdateWhenThenTransaction(
            $this->updateWhenThenDefinitionsBuilder,
            $tableName,
            $rows,
            $identifierColumn,
            $identifierColumnType,
            $updateColumns,
            $updateColumnTypes,
            $isIdempotent,
        );
    }
}
