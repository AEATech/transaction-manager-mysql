<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\MySQLIdentifierQuoter;
use AEATech\TransactionManager\Query;
use AEATech\TransactionManager\StatementReusePolicy;
use AEATech\TransactionManager\TransactionInterface;
use InvalidArgumentException;

/**
 * MySQL-specific DELETE transaction with LIMIT support.
 *
 * Generates SQL:
 *   DELETE FROM `table` WHERE `identifier_column` IN (?, ?, ...) LIMIT N
 *
 * IMPORTANT:
 * - This is MySQL-only due to DELETE ... LIMIT syntax.
 */
class DeleteWithLimitTransaction implements TransactionInterface
{
    /**
     * @param array<int, mixed> $identifiers
     */
    public function __construct(
        private readonly MySQLIdentifierQuoter $quoter,
        private readonly string $tableName,
        private readonly string $identifierColumn,
        private readonly mixed $identifierColumnType,
        private readonly array $identifiers,
        private readonly int $limit,
        private readonly bool $isIdempotent = true,
        private readonly StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ) {
        if ([] === $this->identifiers) {
            throw new InvalidArgumentException('Identifiers must not be empty.');
        }

        if (0 >= $this->limit) {
            throw new InvalidArgumentException('Limit must be a positive integer.');
        }
    }

    public function build(): Query
    {
        $identifiersCount = count($this->identifiers);

        $params = array_values($this->identifiers);
        $types = array_fill(0, $identifiersCount, $this->identifierColumnType);
        $placeholders = array_fill(0, $identifiersCount, '?');

        $sql = sprintf(
            'DELETE FROM %s WHERE %s IN (%s) LIMIT %d',
            $this->quoter->quoteIdentifier($this->tableName),
            $this->quoter->quoteIdentifier($this->identifierColumn),
            implode(', ', $placeholders),
            $this->limit
        );

        return new Query($sql, $params, $types, $this->statementReusePolicy);
    }

    public function isIdempotent(): bool
    {
        return $this->isIdempotent;
    }
}
