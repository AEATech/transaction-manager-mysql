<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\Internal\InsertValuesBuilder;
use AEATech\TransactionManager\MySQL\Transaction\InsertMode;
use AEATech\TransactionManager\MySQL\Transaction\InsertTransactionFactory;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(InsertTransactionFactory::class)]
class InsertTransactionFactoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private InsertValuesBuilder&m\MockInterface $insertValuesBuilder;
    private InsertTransactionFactory $insertTransactionFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertValuesBuilder = m::mock(InsertValuesBuilder::class);
        $this->insertTransactionFactory = new InsertTransactionFactory($this->insertValuesBuilder);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function factoryRegularInsertTransaction(): void
    {
        $rows = [['id' => 1, 'name' => 'Alex']];

        $this->insertValuesBuilder->shouldReceive('build')
            ->once()
            ->with($rows, ['id' => 1])
            ->andReturn([
                '(?, ?)',
                [1, 'Alex'],
                [0 => 1],
                ['id', 'name'],
            ]);

        $tx = $this->insertTransactionFactory->factory('users', $rows, ['id' => 1]);

        $q = $tx->build();

        self::assertSame('INSERT INTO `users` (`id`, `name`) VALUES (?, ?)', $q->sql);
        self::assertSame([1, 'Alex'], $q->params);
        self::assertSame([0 => 1], $q->types);
        self::assertFalse($tx->isIdempotent());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function factoryCreatesInsertIgnoreTransactionAndPropagatesIdempotency(): void
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

        $tx = $this->insertTransactionFactory->factory('t', $rows, [], InsertMode::Ignore, true);

        $q = $tx->build();

        self::assertSame('INSERT IGNORE INTO `t` (`id`, `name`) VALUES (?, ?)', $q->sql);
        self::assertSame([10, 'B'], $q->params);
        self::assertSame([], $q->types);
        self::assertTrue($tx->isIdempotent());
    }
}
