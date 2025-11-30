<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\Internal\InsertValuesBuilder;

class InsertOnDuplicateKeyUpdateTransactionFactory
{
    public function __construct(
        private readonly InsertValuesBuilder $insertValuesBuilder
    ) {
    }

    public function factory(
        string $tableName,
        array $rows,
        array $updateColumns,
        array $columnTypes = [],
        bool $isIdempotent = false,
    ): InsertOnDuplicateKeyUpdateTransaction {
        return new InsertOnDuplicateKeyUpdateTransaction(
            $this->insertValuesBuilder,
            $tableName,
            $rows,
            $updateColumns,
            $columnTypes,
            $isIdempotent
        );
    }
}
