<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\Internal\MySQLQuoteIdentifierTrait;
use AEATech\TransactionManager\Query;
use AEATech\TransactionManager\TransactionInterface;
use InvalidArgumentException;

class InsertTransaction implements TransactionInterface
{
    use MySQLQuoteIdentifierTrait;

    /**
     * @param string $tableName e.g. 'users'
     * @param array<array<string, mixed>> $rows
     *        [
     *            ['name' => 'Alex', 'age' => 30],
     *            ['name' => 'Bob', 'age' => 25],
     *        ]
     * @param array<string, int|string> $columnTypes column => Doctrine/DBAL type or PDO::PARAM_*
     * @param bool $isIdempotent
     */
    public function __construct(
        private readonly string $tableName,
        private readonly array $rows,
        private readonly array $columnTypes = [],
        private readonly bool $isIdempotent = false
    ) {
        if ([] === $this->rows) {
            throw new InvalidArgumentException('InsertTransaction requires non-empty $rows.');
        }

        // Minimal data integrity check: first row defines the column contract.
        $firstRow = $this->rows[array_key_first($this->rows)];

        if ([] === $firstRow || false === is_array($firstRow)) {
            throw new InvalidArgumentException('InsertTransaction: first row must be a non-empty array.');
        }
    }

    public function build(): Query
    {
        $columns = array_keys($this->rows[array_key_first($this->rows)]); // the first row fixes the order of columns

        $quotedColumns = array_map(static fn (string $column) => self::quoteIdentifier($column), $columns);

        $valuesSqlParts = [];
        $params = [];
        $types = [];

        $paramIndex = 0;

        foreach ($this->rows as $rowIndex => $row) {
            if (false === is_array($row)) {
                throw new InvalidArgumentException(sprintf(
                    'InsertTransaction: row %s must be an array, %s given.',
                    $rowIndex,
                    get_debug_type($row)
                ));
            }

            $placeholders = [];

            foreach ($columns as $column) {
                if (false === isset($row[$column]) && false === array_key_exists($column, $row)) {
                    // Can't substitute null, it's a hidden bug.
                    throw new InvalidArgumentException(sprintf(
                        'InsertTransaction: row %s is missing required column "%s".',
                        $rowIndex,
                        $column
                    ));
                }

                $placeholders[] = '?';
                $params[] = $row[$column];

                // If a type is defined for this column, use it.
                // If a type is not defined — do not write to $types,
                // Doctrine will bind the parameter as ParameterType::STRING.
                if (isset($this->columnTypes[$column])) {
                    $types[$paramIndex] = $this->columnTypes[$column];
                }

                $paramIndex++;
            }

            $valuesSqlParts[] = '(' . implode(', ', $placeholders) . ')';
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            self::quoteIdentifier($this->tableName),
            implode(', ', $quotedColumns),
            implode(', ', $valuesSqlParts),
        );

        return new Query(
            sql: $sql,
            params: $params,
            types: $types, // maybe partially/fully empty → everything without a type is bound as STRING
        );
    }

    public function isIdempotent(): bool
    {
        return $this->isIdempotent;
    }
}
