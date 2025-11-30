<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\Internal\InsertValuesBuilder;
use AEATech\TransactionManager\MySQL\Transaction\InsertMode;
use AEATech\TransactionManager\MySQL\Transaction\InsertTransaction;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(InsertTransaction::class)]
class InsertTransactionTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private InsertValuesBuilder&m\MockInterface $insertValuesBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertValuesBuilder = m::mock(InsertValuesBuilder::class);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function buildComposesSqlWithQuotedTableAndColumnsUsingBuilderOutput(): void
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
                [0 => 1],                // types (simplified map for the test)
                ['id', 'na`me'],         // columns to be quoted by transaction
            ]);

        $tx = new InsertTransaction($this->insertValuesBuilder, 'users', $rows, ['id' => 1], InsertMode::Regular, true);

        // Act
        $q = $tx->build();

        // Assert
        self::assertSame('INSERT INTO `users` (`id`, `na``me`) VALUES (?, ?)', $q->sql);
        self::assertSame([1, 'Alex'], $q->params);
        self::assertSame([0 => 1], $q->types);
        self::assertTrue($tx->isIdempotent());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function buildUsesInsertIgnoreWhenModeIsIgnore(): void
    {
        $rows = [['id' => 10, 'name' => 'B']];

        $this->insertValuesBuilder->shouldReceive('build')
            ->once()
            ->with($rows, [])
            ->andReturn([
                '(?, ?)',
                [10, 'B'],
                [],
                ['id', 'name'],
            ]);

        $tx = new InsertTransaction($this->insertValuesBuilder, 't', $rows, [], InsertMode::Ignore, false);
        $q = $tx->build();

        self::assertSame('INSERT IGNORE INTO `t` (`id`, `name`) VALUES (?, ?)', $q->sql);
        self::assertSame([10, 'B'], $q->params);
        self::assertSame([], $q->types);
        self::assertFalse($tx->isIdempotent());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    #[DataProvider('isIdempotentDataProvider')]
    public function isIdempotent(bool $isIdempotent): void
    {
        $rows = [['a' => 1]];
        $this->insertValuesBuilder->shouldReceive('build')->andReturn(['(?)', [1], [], ['a']]);

        $insertTransaction = new InsertTransaction(
            $this->insertValuesBuilder,
            't',
            $rows,
            [],
            InsertMode::Regular,
            $isIdempotent
        );

        self::assertSame($isIdempotent, $insertTransaction->isIdempotent());
    }

    public static function isIdempotentDataProvider(): array
    {
        return [
            [
                'isIdempotent' => true,
            ],
            [
                'isIdempotent' => false,
            ]
        ];
    }
}
