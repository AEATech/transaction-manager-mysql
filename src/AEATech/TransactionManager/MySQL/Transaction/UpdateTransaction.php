<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\Internal\MySQLQuoteIdentifierTrait;
use AEATech\TransactionManager\Query;
use AEATech\TransactionManager\TransactionInterface;
use Doctrine\DBAL\ParameterType;
use InvalidArgumentException;

class UpdateTransaction implements TransactionInterface
{
    use MySQLQuoteIdentifierTrait;

    public const MESSAGE_IDENTIFIERS_MUST_NOT_BE_EMPTY = 'Identifiers must not be empty.';
    public const MESSAGE_COLUMNS_WITH_VALUES_FOR_UPDATE_MUST_NOT_BE_EMPTY
        = 'Columns with values for update must not be empty.';

    public function __construct(
        private readonly string $tableName,
        private readonly string $identifierColumn,
        private readonly int|ParameterType $identifierColumnType,
        private readonly array $identifiers,
        private readonly array $columnsWithValuesForUpdate,
        private readonly array $columnTypes = [],
        private readonly bool $isIdempotent = true,
    ) {
    }

    public function build(): Query
    {
        if (empty($this->identifiers)) {
            throw new InvalidArgumentException(self::MESSAGE_IDENTIFIERS_MUST_NOT_BE_EMPTY);
        }

        if (empty($this->columnsWithValuesForUpdate)) {
            throw new InvalidArgumentException(self::MESSAGE_COLUMNS_WITH_VALUES_FOR_UPDATE_MUST_NOT_BE_EMPTY);
        }

        $paramIndex = 0;
        $params = [];
        $types = [];
        $updateSetParts = [];

        foreach ($this->columnsWithValuesForUpdate as $column => $value) {
            $quotedColumn = self::quoteIdentifier($column);

            $updateSetParts[] = sprintf('%s = ?', $quotedColumn);

            $params[$paramIndex] = $value;

            if (isset($this->columnTypes[$column])) {
                $types[$paramIndex] = $this->columnTypes[$column];
            }

            $paramIndex++;
        }

        $placeholders = [];

        foreach ($this->identifiers as $identifier) {
            $params[$paramIndex] = $identifier;
            $types[$paramIndex] = $this->identifierColumnType;
            $placeholders[] = '?';

            $paramIndex++;
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s IN (%s)',
            self::quoteIdentifier($this->tableName),
            implode(', ', $updateSetParts),
            self::quoteIdentifier($this->identifierColumn),
            implode(', ', $placeholders),
        );

        return new Query($sql, $params, $types);
    }

    public function isIdempotent(): bool
    {
        return $this->isIdempotent;
    }
}
