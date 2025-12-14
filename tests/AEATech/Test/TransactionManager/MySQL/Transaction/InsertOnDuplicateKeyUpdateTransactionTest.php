<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\MySQLIdentifierQuoter;
use AEATech\TransactionManager\MySQL\Transaction\InsertOnDuplicateKeyUpdateTransaction;
use AEATech\TransactionManager\StatementReusePolicy;
use AEATech\TransactionManager\Transaction\Internal\InsertValuesBuilder;
use InvalidArgumentException;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(InsertOnDuplicateKeyUpdateTransaction::class)]
class InsertOnDuplicateKeyUpdateTransactionTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private InsertValuesBuilder&m\MockInterface $insertValuesBuilder;
    private MySQLIdentifierQuoter $quoter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertValuesBuilder = m::mock(InsertValuesBuilder::class);
        $this->quoter = new MySQLIdentifierQuoter();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function buildComposesSqlAndAssignmentsUsingValuesKeyword(): void
    {
        // Arrange
        $rows = [
            ['id' => 1, 'na`me' => 'Alex'],
        ];

        $this->insertValuesBuilder->shouldReceive('build')
            ->once()
            ->with($rows, ['id' => 1])
            ->andReturn([
                '(?, ?)',                // values SQL
                [1, 'Alex'],             // params
                [0 => 1],                // types
                ['id', 'na`me'],         // columns
            ]);

        $tx = new InsertOnDuplicateKeyUpdateTransaction(
            $this->insertValuesBuilder,
            $this->quoter,
            'users',
            $rows,
            ['na`me'],
            ['id' => 1],
            true,
            StatementReusePolicy::PerTransaction
        );

        // Act
        $q = $tx->build();

        // Assert
        self::assertSame(
            'INSERT INTO `users` (`id`, `na``me`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `na``me` = VALUES(`na``me`)',
            $q->sql
        );
        self::assertSame([1, 'Alex'], $q->params);
        self::assertSame([0 => 1], $q->types);
        self::assertTrue($tx->isIdempotent());
        self::assertSame(StatementReusePolicy::PerTransaction, $q->statementReusePolicy);
    }

    #[Test]
    public function constructorThrowsWhenUpdateColumnsIsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('InsertOnDuplicateKeyUpdateTransaction requires non-empty $updateColumns.');

        new InsertOnDuplicateKeyUpdateTransaction(
            $this->insertValuesBuilder,
            $this->quoter,
            't',
            [['a' => 1]],
            [],
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function buildThrowsWhenUpdateColumnMissingFromRows(): void
    {
        $rows = [
            ['id' => 1],
        ];

        $this->insertValuesBuilder->shouldReceive('build')
            ->once()
            ->with($rows, [])
            ->andReturn([
                '(?)',
                [1],
                [],
                ['id'],
            ]);

        $tx = new InsertOnDuplicateKeyUpdateTransaction(
            $this->insertValuesBuilder,
            $this->quoter,
            't',
            $rows,
            ['name'],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Update columns must exist in rows. Missing: name');

        $tx->build();
    }

    #[Test]
    #[DataProvider('isIdempotentDataProvider')]
    public function isIdempotent(bool $isIdempotent): void
    {
        $rows = [['a' => 1]];

        $this->insertValuesBuilder->shouldReceive('build')->andReturn(['(?)', [1], [], ['a']]);

        $tx = new InsertOnDuplicateKeyUpdateTransaction(
            $this->insertValuesBuilder,
            $this->quoter,
            't',
            $rows,
            ['a'],
            [],
            $isIdempotent,
        );

        self::assertSame($isIdempotent, $tx->isIdempotent());
    }

    public static function isIdempotentDataProvider(): array
    {
        return [
            ['isIdempotent' => true],
            ['isIdempotent' => false],
        ];
    }
}
