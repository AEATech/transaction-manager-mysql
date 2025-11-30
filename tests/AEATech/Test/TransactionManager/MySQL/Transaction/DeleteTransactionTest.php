<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\Transaction\DeleteTransaction;
use AEATech\TransactionManager\Query;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeleteTransaction::class)]
class DeleteTransactionTest extends TestCase
{
    #[Test]
    public function build(): void
    {
        $identifiers = [
            100501 => 1,
            100502 => 2,
            100503 => 3,
        ];

        $transaction = new DeleteTransaction(
            'test_table',
            'id',
            PDO::PARAM_INT,
            $identifiers
        );

        $expectedParams = [];
        $expectedTypes = [];
        foreach ($identifiers as $identifier) {
            $expectedParams[] = $identifier;
            $expectedTypes[] = PDO::PARAM_INT;
        }

        $expectedSql = 'DELETE FROM `test_table` WHERE `id` IN (?, ?, ?)';

        $expectedQuery = new Query($expectedSql, $expectedParams, $expectedTypes);

        /** @noinspection PhpUnhandledExceptionInspection */
        $actualQuery = $transaction->build();

        self::assertEquals($expectedQuery, $actualQuery);
    }

    #[Test]
    #[DataProvider('isIdempotentDataProvider')]
    public function isIdempotent(bool $isIdempotent): void
    {
        $transaction = new DeleteTransaction(
            'test_table',
            'id',
            PDO::PARAM_INT,
            [1, 2, 3],
            $isIdempotent
        );

        self::assertSame($isIdempotent, $transaction->isIdempotent());
    }

    public static function isIdempotentDataProvider(): array
    {
        return [
            [
                'isIdempotent' => true,
            ],
            [
                'isIdempotent' => false,
            ],
        ];
    }

    #[Test]
    public function buildFailedWithEmptyIdentifiers(): void
    {
        $transaction = new DeleteTransaction(
            'test_table',
            'id',
            PDO::PARAM_INT,
            []
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(DeleteTransaction::MESSAGE_IDENTIFIERS_MUST_NOT_BE_EMPTY);

        /** @noinspection PhpUnhandledExceptionInspection */
        $transaction->build();
    }
}
