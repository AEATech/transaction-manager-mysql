<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\Internal\MySQLQuoteIdentifierTrait;
use AEATech\TransactionManager\MySQL\Internal\UpdateWhenThenDefinitionsBuilder;
use AEATech\TransactionManager\Query;
use AEATech\TransactionManager\TransactionInterface;
use Doctrine\DBAL\ParameterType;

class UpdateWhenThenTransaction implements TransactionInterface
{
    use MySQLQuoteIdentifierTrait;

    public function __construct(
        private readonly UpdateWhenThenDefinitionsBuilder $definitionsBuilder,
        private readonly string $tableName,
        private readonly array $rows,
        private readonly string $identifierColumn,
        private readonly int|ParameterType $identifierColumnType,
        private readonly array $updateColumns,
        private readonly array $updateColumnTypes = [],
        private readonly bool $isIdempotent = true,
    ) {
    }

    public function build(): Query
    {
        [
            $identifiers,
            $updateDefinitions,
        ] = $this->definitionsBuilder->build($this->rows, $this->identifierColumn, $this->updateColumns);

        $quotedIdentifierColumn = self::quoteIdentifier($this->identifierColumn);
        $whenThenPart = sprintf('WHEN %s = ? THEN ?', $quotedIdentifierColumn);
        $params = [];
        $types = [];
        $setCaseParts = [];

        foreach ($updateDefinitions as $column => $values) {
            $quotedColumn = self::quoteIdentifier($column);
            $columnType = $this->updateColumnTypes[$column] ?? null;

            $whenThenParts = [];
            foreach ($values as [$identifier, $value]) {
                $whenThenParts[] = $whenThenPart;

                $params[] = $identifier;
                $types[] = $this->identifierColumnType;

                $params[] = $value;
                $types[] = $columnType;
            }

            $setCaseParts[] = sprintf(
                '%s = CASE %s ELSE %s END',
                $quotedColumn,
                implode(' ', $whenThenParts),
                $quotedColumn
            );
        }

        $placeholders = [];

        foreach ($identifiers as $identifier) {
            $params[] = $identifier;
            $types[] = $this->identifierColumnType;
            $placeholders[] = '?';
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s IN (%s)',
            self::quoteIdentifier($this->tableName),
            implode(', ', $setCaseParts),
            $quotedIdentifierColumn,
            implode(', ', $placeholders),
        );

        $types = array_filter($types);

        return new Query($sql, $params, $types);
    }

    public function isIdempotent(): bool
    {
        return $this->isIdempotent;
    }
}
