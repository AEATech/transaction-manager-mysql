<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\Transaction\SqlTransaction;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(SqlTransaction::class)]
class SqlTransactionTest extends TestCase
{
    private const SQL = '...';
    private const PARAMS = [1];
    private const PARAM_TYPES = [ParameterType::INTEGER];

    private SqlTransaction $transaction;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->transaction = new SqlTransaction(self::SQL, self::PARAMS, self::PARAM_TYPES);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function build(): void
    {
        $expected = [self::SQL, self::PARAMS, self::PARAM_TYPES];

        $query = $this->transaction->build();

        self::assertSame(
            $expected,
            [
                $query->sql,
                $query->params,
                $query->types,
            ]
        );
    }

    #[Test]
    #[DataProvider('isIdempotentDataProvider')]
    public function isIdempotent(bool $expected): void
    {
        $sqlTransaction = new SqlTransaction(self::SQL, self::PARAM_TYPES, self::PARAM_TYPES, $expected);

        self::assertSame($expected, $sqlTransaction->isIdempotent());
    }

    public static function isIdempotentDataProvider(): array
    {
        return [
            [
                'expected' => true,
            ],
            [
                'expected' => false,
            ],
        ];
    }
}
