<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\MySQLIdentifierQuoter;

class DeleteWithLimitTransactionFactory
{
    public function __construct(
        private readonly MySQLIdentifierQuoter $quoter,
    ) {
    }

    public function factory(
        string $tableName,
        string $identifierColumn,
        mixed $identifierColumnType,
        array $identifiers,
        int $limit,
        bool $isIdempotent = true,
    ): DeleteWithLimitTransaction {
        return new DeleteWithLimitTransaction(
            $this->quoter,
            $tableName,
            $identifierColumn,
            $identifierColumnType,
            $identifiers,
            $limit,
            $isIdempotent
        );
    }
}
