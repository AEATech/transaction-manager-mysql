<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL;

use AEATech\TransactionManager\MySQL\MySQLTransactionsFactory;
use AEATech\TransactionManager\MySQL\MySQLTransactionsFactoryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MySQLTransactionsFactoryBuilder::class)]
class MySQLTransactionsFactoryBuilderTest extends TestCase
{
    #[Test]
    public function build(): void
    {
        self::assertInstanceOf(MySQLTransactionsFactory::class, MySQLTransactionsFactoryBuilder::build());
    }
}
