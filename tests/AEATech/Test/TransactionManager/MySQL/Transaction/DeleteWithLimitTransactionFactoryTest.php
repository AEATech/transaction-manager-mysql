<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\MySQLIdentifierQuoter;
use AEATech\TransactionManager\MySQL\Transaction\DeleteWithLimitTransactionFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(DeleteWithLimitTransactionFactory::class)]
class DeleteWithLimitTransactionFactoryTest extends TestCase
{
    /**
     * @throws Throwable
     */
    #[Test]
    public function factoryCreatesDeleteWithLimitTransaction(): void
    {
        $factory = new DeleteWithLimitTransactionFactory(new MySQLIdentifierQuoter());

        $tx = $factory->factory('logs', 'id', 1, [10, 11, 12], 2);

        $q = $tx->build();

        self::assertSame('DELETE FROM `logs` WHERE `id` IN (?, ?, ?) LIMIT 2', $q->sql);
        self::assertSame([10, 11, 12], $q->params);
        self::assertSame([1, 1, 1], $q->types);
        self::assertTrue($tx->isIdempotent());
    }
}
