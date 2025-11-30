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
}