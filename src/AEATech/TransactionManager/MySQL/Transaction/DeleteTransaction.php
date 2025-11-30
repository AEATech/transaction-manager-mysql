<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\Internal\MySQLQuoteIdentifierTrait;
use AEATech\TransactionManager\Query;
use AEATech\TransactionManager\TransactionInterface;
use Doctrine\DBAL\ParameterType;
use InvalidArgumentException;

class DeleteTransaction implements TransactionInterface
{
    use MySQLQuoteIdentifierTrait;

    public const MESSAGE_IDENTIFIERS_MUST_NOT_BE_EMPTY = 'Identifiers must not be empty.';

    public function __construct(
        private readonly string $tableName,
        private readonly string $identifierColumn,
        private readonly int|ParameterType $identifierColumnType,
        private readonly array $identifiers,
        private readonly bool $isIdempotent = true,
    ) {
    }

    public function build(): Query
    {
        $identifiersCount = count($this->identifiers);

        if (0 === $identifiersCount) {
            throw new InvalidArgumentException(self::MESSAGE_IDENTIFIERS_MUST_NOT_BE_EMPTY);
        }

        $params = array_values($this->identifiers);
        $types = array_fill(0, $identifiersCount, $this->identifierColumnType);
        $placeholders = array_fill(0, $identifiersCount, '?');

        $sql = sprintf(
            'DELETE FROM %s WHERE %s IN (%s)',
            self::quoteIdentifier($this->tableName),
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
