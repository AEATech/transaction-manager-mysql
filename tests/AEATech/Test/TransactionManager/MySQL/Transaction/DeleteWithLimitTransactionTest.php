<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\MySQLIdentifierQuoter;
use AEATech\TransactionManager\MySQL\Transaction\DeleteWithLimitTransaction;
use AEATech\TransactionManager\StatementReusePolicy;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(DeleteWithLimitTransaction::class)]
class DeleteWithLimitTransactionTest extends TestCase
{
    /**
     * @throws Throwable
     */
    #[Test]
    public function build(): void
    {
        $tx = new DeleteWithLimitTransaction(
            new MySQLIdentifierQuoter(),
            'user`s',
            'id',
            1,
            [10, 11, 12],
            2,
            true,
            StatementReusePolicy::PerTransaction
        );

        $q = $tx->build();

        self::assertSame('DELETE FROM `user``s` WHERE `id` IN (?, ?, ?) LIMIT 2', $q->sql);
        self::assertSame([10, 11, 12], $q->params);
        self::assertSame([1, 1, 1], $q->types);
        self::assertTrue($tx->isIdempotent());
        self::assertSame(StatementReusePolicy::PerTransaction, $q->statementReusePolicy);
    }

    #[Test]
    public function constructorThrowsWhenIdentifiersEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Identifiers must not be empty.');

        new DeleteWithLimitTransaction(new MySQLIdentifierQuoter(), 't', 'id', 1, [], 1);
    }

    #[Test]
    #[DataProvider('invalidLimitsDataProvider')]
    public function constructorThrowsWhenLimitNotPositive(int $limit): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be a positive integer.');

        new DeleteWithLimitTransaction(new MySQLIdentifierQuoter(), 't', 'id', 1, [1], $limit);
    }

    public static function invalidLimitsDataProvider(): array
    {
        return [
            ['limit' => 0],
            ['limit' => -1],
        ];
    }
}
