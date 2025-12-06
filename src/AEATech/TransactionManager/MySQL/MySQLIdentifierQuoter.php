<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL;

use AEATech\TransactionManager\Transaction\IdentifierQuoterInterface;

class MySQLIdentifierQuoter implements IdentifierQuoterInterface
{
    public function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    public function quoteIdentifiers(array $identifiers): array
    {
        return array_map(fn (string $identifier) => $this->quoteIdentifier($identifier), $identifiers);
    }
}
