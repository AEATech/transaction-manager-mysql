<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL\Internal;

/**
 * @internal
 */
trait MySQLQuoteIdentifierTrait
{
    private static function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private static function quoteIdentifiers(array $identifiers): array
    {
        return array_map(static fn (string $identifier) => self::quoteIdentifier($identifier), $identifiers);
    }
}