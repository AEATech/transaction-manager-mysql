<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\MySQLIdentifierQuoter;
use AEATech\TransactionManager\MySQL\Transaction\InsertIgnoreTransaction;
use AEATech\TransactionManager\Transaction\Internal\InsertValuesBuilder;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(InsertIgnoreTransaction::class)]
class InsertIgnoreTransactionTest extends TestCase
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
    public function build(): void
    {
        $rows = [
            ['id' => 1, 'na`me' => 'Alex'],
            ['id' => 2, 'na`me' => 'Bob'],
        ];

        $this->insertValuesBuilder->shouldReceive('build')
            ->once()
            ->with($rows, ['id' => 1])
            ->andReturn([
                '(?, ?), (?, ?)',
                [1, 'Alex', 2, 'Bob'],
                [0 => 1],
                ['id', 'na`me'],
            ]);

        $tx = new InsertIgnoreTransaction(
            $this->insertValuesBuilder,
            $this->quoter,
            'users',
            $rows,
            ['id' => 1],
            false,
        );

        $q = $tx->build();

        $expected = 'INSERT IGNORE INTO `users` (`id`, `na``me`) VALUES (?, ?), (?, ?)';

        self::assertSame($expected, $q->sql);
        self::assertSame([1, 'Alex', 2, 'Bob'], $q->params);
        self::assertSame([0 => 1], $q->types);
        self::assertFalse($tx->isIdempotent());
    }
}
