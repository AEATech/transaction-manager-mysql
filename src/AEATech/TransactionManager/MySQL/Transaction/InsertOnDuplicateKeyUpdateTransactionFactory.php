<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\MySQLIdentifierQuoter;
use AEATech\TransactionManager\StatementReusePolicy;
use AEATech\TransactionManager\Transaction\Internal\InsertValuesBuilder;

class InsertOnDuplicateKeyUpdateTransactionFactory
{
    public function __construct(
        private readonly InsertValuesBuilder $insertValuesBuilder,
        private readonly MySQLIdentifierQuoter $quoter,
    ) {
    }

    /**
     * @param array<array<string, mixed>> $rows
     * @param string[] $updateColumns
     * @param array<string, mixed> $columnTypes
     */
    public function factory(
        string $tableName,
        array $rows,
        array $updateColumns,
        array $columnTypes = [],
        bool $isIdempotent = false,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): InsertOnDuplicateKeyUpdateTransaction {
        return new InsertOnDuplicateKeyUpdateTransaction(
            $this->insertValuesBuilder,
            $this->quoter,
            $tableName,
            $rows,
            $updateColumns,
            $columnTypes,
            $isIdempotent,
            $statementReusePolicy
        );
    }
}
