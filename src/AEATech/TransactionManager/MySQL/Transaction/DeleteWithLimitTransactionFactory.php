<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\MySQLIdentifierQuoter;
use AEATech\TransactionManager\StatementReusePolicy;

class DeleteWithLimitTransactionFactory
{
    public function __construct(
        private readonly MySQLIdentifierQuoter $quoter,
    ) {
    }

    /**
     * @param array<int, mixed> $identifiers
     */
    public function factory(
        string $tableName,
        string $identifierColumn,
        mixed $identifierColumnType,
        array $identifiers,
        int $limit,
        bool $isIdempotent = true,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): DeleteWithLimitTransaction {
        return new DeleteWithLimitTransaction(
            $this->quoter,
            $tableName,
            $identifierColumn,
            $identifierColumnType,
            $identifiers,
            $limit,
            $isIdempotent,
            $statementReusePolicy
        );
    }
}
