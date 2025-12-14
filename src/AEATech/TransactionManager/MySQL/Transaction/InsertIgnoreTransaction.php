<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\MySQLIdentifierQuoter;
use AEATech\TransactionManager\Query;
use AEATech\TransactionManager\StatementReusePolicy;
use AEATech\TransactionManager\Transaction\Internal\InsertValuesBuilder;
use AEATech\TransactionManager\TransactionInterface;

class InsertIgnoreTransaction implements TransactionInterface
{
    public function __construct(
        private readonly InsertValuesBuilder $insertValuesBuilder,
        private readonly MySQLIdentifierQuoter $quoter,
        private readonly string $tableName,
        private readonly array $rows,
        private readonly array $columnTypes = [],
        private readonly bool $isIdempotent = false,
        private readonly StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ) {
    }

    public function build(): Query
    {
        [$valuesSql, $params, $types, $columns] =
            $this->insertValuesBuilder->build($this->rows, $this->columnTypes);

        $quotedColumns = $this->quoter->quoteIdentifiers($columns);

        $sql = sprintf(
            'INSERT IGNORE INTO %s (%s) VALUES %s',
            $this->quoter->quoteIdentifier($this->tableName),
            implode(', ', $quotedColumns),
            $valuesSql,
        );

        return new Query($sql, $params, $types, $this->statementReusePolicy);
    }

    public function isIdempotent(): bool
    {
        return $this->isIdempotent;
    }
}
