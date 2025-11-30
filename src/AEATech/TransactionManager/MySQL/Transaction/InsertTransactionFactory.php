<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\Internal\InsertValuesBuilder;

class InsertTransactionFactory
{
    public function __construct(
        private readonly InsertValuesBuilder $insertValuesBuilder,
    ) {
    }

    public function factory(
        string $tableName,
        array $rows,
        array $columnTypes = [],
        InsertMode $insertMode = InsertMode::Regular,
        bool $isIdempotent = false,
    ): InsertTransaction {
        return new InsertTransaction(
            $this->insertValuesBuilder,
            $tableName,
            $rows,
            $columnTypes,
            $insertMode,
            $isIdempotent
        );
    }
}
