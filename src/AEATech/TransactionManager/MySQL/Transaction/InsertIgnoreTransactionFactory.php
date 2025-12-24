<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\MySQLIdentifierQuoter;
use AEATech\TransactionManager\StatementReusePolicy;
use AEATech\TransactionManager\Transaction\Internal\InsertValuesBuilder;

class InsertIgnoreTransactionFactory
{
    public function __construct(
        private readonly InsertValuesBuilder $insertValuesBuilder,
        private readonly MySQLIdentifierQuoter $quoter,
    ) {
    }

    /**
     * @param array<array<string, mixed>> $rows
     * @param array<string, int|string> $columnTypes
     */
    public function factory(
        string $tableName,
        array $rows,
        array $columnTypes = [],
        bool $isIdempotent = false,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): InsertIgnoreTransaction {
        return new InsertIgnoreTransaction(
            $this->insertValuesBuilder,
            $this->quoter,
            $tableName,
            $rows,
            $columnTypes,
            $isIdempotent,
            $statementReusePolicy
        );
    }
}
