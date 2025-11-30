<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL\Transaction;

use Doctrine\DBAL\ParameterType;

class DeleteTransactionFactory
{
    public function factory(
        string $tableName,
        string $identifierColumn,
        int|ParameterType $identifierColumnType,
        array $identifiers,
        bool $isIdempotent = true,
    ): DeleteTransaction {
        return new DeleteTransaction(
            $tableName,
            $identifierColumn,
            $identifierColumnType,
            $identifiers,
            $isIdempotent
        );
    }
}
