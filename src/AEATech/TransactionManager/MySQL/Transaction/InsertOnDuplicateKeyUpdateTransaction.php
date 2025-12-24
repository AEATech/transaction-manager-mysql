<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\MySQLIdentifierQuoter;
use AEATech\TransactionManager\Query;
use AEATech\TransactionManager\StatementReusePolicy;
use AEATech\TransactionManager\Transaction\Internal\InsertValuesBuilder;
use AEATech\TransactionManager\TransactionInterface;
use InvalidArgumentException;

/**
 * Upsert by PK/UNIQUE: INSERT ... ON DUPLICATE KEY UPDATE col = VALUES(col), ...
 */
class InsertOnDuplicateKeyUpdateTransaction implements TransactionInterface
{
    /**
     * @param array<array<string, mixed>> $rows
     * @param string[] $updateColumns
     * @param array<string, int|string> $columnTypes
     */
    public function __construct(
        private readonly InsertValuesBuilder $insertValuesBuilder,
        private readonly MySQLIdentifierQuoter $quoter,
        private readonly string $tableName,
        private readonly array $rows,
        private readonly array $updateColumns,
        private readonly array $columnTypes = [],
        private readonly bool $isIdempotent = false,
        private readonly StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ) {
        if ([] === $this->updateColumns) {
            throw new InvalidArgumentException(
                'InsertOnDuplicateKeyUpdateTransaction requires non-empty $updateColumns.'
            );
        }
    }

    public function build(): Query
    {
        [$valuesSql, $params, $types, $columns] = $this->insertValuesBuilder->build($this->rows, $this->columnTypes);

        $missing = array_diff($this->updateColumns, $columns);

        if ([] !== $missing) {
            throw new InvalidArgumentException(
                'Update columns must exist in rows. Missing: ' . implode(', ', $missing)
            );
        }

        $quotedColumns = $this->quoter->quoteIdentifiers($columns);

        // ON DUPLICATE KEY UPDATE col1 = VALUES(col1), col2 = VALUES(col2) ...
        $updateAssignments = [];

        foreach ($this->updateColumns as $column) {
            $quoted = $this->quoter->quoteIdentifier($column);
            $updateAssignments[] = sprintf('%s = VALUES(%s)', $quoted, $quoted);
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s ON DUPLICATE KEY UPDATE %s',
            $this->quoter->quoteIdentifier($this->tableName),
            implode(', ', $quotedColumns),
            $valuesSql,
            implode(', ', $updateAssignments),
        );

        return new Query($sql, $params, $types, $this->statementReusePolicy);
    }

    public function isIdempotent(): bool
    {
        return $this->isIdempotent;
    }
}
