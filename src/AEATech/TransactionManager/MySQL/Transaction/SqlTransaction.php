<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\Query;
use AEATech\TransactionManager\TransactionInterface;

class SqlTransaction implements TransactionInterface
{
    public function __construct(
        private readonly string $sql,
        private readonly array $params = [],
        private readonly array $types = [],
        private readonly bool $isIdempotent = false,
    ) {
    }

    public function build(): Query
    {
        return new Query($this->sql, $this->params, $this->types);
    }

    public function isIdempotent(): bool
    {
        return $this->isIdempotent;
    }
}
