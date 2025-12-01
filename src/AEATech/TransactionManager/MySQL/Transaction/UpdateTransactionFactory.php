<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL\Transaction;

use Doctrine\DBAL\ParameterType;

class UpdateTransactionFactory
{
    public function factory(
        string $tableName,
        string $identifierColumn,
        int|ParameterType $identifierColumnType,
        array $identifiers,
        array $columnsWithValuesForUpdate,
        array $columnTypes = [],
        bool $isIdempotent = true,
    ): UpdateTransaction {
        return new UpdateTransaction(
            $tableName,
            $identifierColumn,
            $identifierColumnType,
            $identifiers,
            $columnsWithValuesForUpdate,
            $columnTypes,
            $isIdempotent,
        );
    }
}
