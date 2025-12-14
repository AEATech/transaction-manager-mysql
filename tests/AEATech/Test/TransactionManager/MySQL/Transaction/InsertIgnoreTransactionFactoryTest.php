<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\MySQLIdentifierQuoter;
use AEATech\TransactionManager\MySQL\Transaction\InsertIgnoreTransactionFactory;
use AEATech\TransactionManager\StatementReusePolicy;
use AEATech\TransactionManager\Transaction\Internal\InsertValuesBuilder;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(InsertIgnoreTransactionFactory::class)]
class InsertIgnoreTransactionFactoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private InsertValuesBuilder&m\MockInterface $insertValuesBuilder;
    private InsertIgnoreTransactionFactory $ignoreTransactionFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertValuesBuilder = m::mock(InsertValuesBuilder::class);
        $this->ignoreTransactionFactory = new InsertIgnoreTransactionFactory(
            $this->insertValuesBuilder,
            new MySQLIdentifierQuoter(),
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function factory(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Alex'],
        ];

        $this->insertValuesBuilder->shouldReceive('build')
            ->once()
            ->with($rows, ['id' => 1])
            ->andReturn([
                '(?, ?)',
                [1, 'Alex'],
                [0 => 1],
                ['id', 'name'],
            ]);

        $tx = $this->ignoreTransactionFactory->factory(
            'users',
            $rows,
            ['id' => 1],
            true,
            StatementReusePolicy::PerTransaction
        );

        $q = $tx->build();

        $expectedSql = 'INSERT IGNORE INTO `users` (`id`, `name`) VALUES (?, ?)';

        self::assertSame($expectedSql, $q->sql);
        self::assertSame([1, 'Alex'], $q->params);
        self::assertSame([0 => 1], $q->types);
        self::assertTrue($tx->isIdempotent());
        self::assertSame(StatementReusePolicy::PerTransaction, $q->statementReusePolicy);
    }
}
