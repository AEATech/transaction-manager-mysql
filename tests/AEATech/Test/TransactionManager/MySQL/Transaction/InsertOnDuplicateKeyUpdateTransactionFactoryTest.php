<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\Internal\InsertValuesBuilder;
use AEATech\TransactionManager\MySQL\Transaction\InsertOnDuplicateKeyUpdateTransactionFactory;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(InsertOnDuplicateKeyUpdateTransactionFactory::class)]
class InsertOnDuplicateKeyUpdateTransactionFactoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private InsertValuesBuilder&m\MockInterface $insertValuesBuilder;
    private InsertOnDuplicateKeyUpdateTransactionFactory $insertOnDuplicateKeyUpdateTransactionFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertValuesBuilder = m::mock(InsertValuesBuilder::class);
        $this->insertOnDuplicateKeyUpdateTransactionFactory = new InsertOnDuplicateKeyUpdateTransactionFactory(
            $this->insertValuesBuilder
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function factoryCreatesUpsertTransactionWithAssignments(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Alex', 'email' => 'a@example.com'],
        ];

        $this->insertValuesBuilder->shouldReceive('build')
            ->once()
            ->with($rows, ['id' => 1])
            ->andReturn([
                '(?, ?, ?)',
                [1, 'Alex', 'a@example.com'],
                [0 => 1],
                ['id', 'name', 'email'],
            ]);

        $tx = $this->insertOnDuplicateKeyUpdateTransactionFactory->factory(
            'users',
            $rows,
            ['name', 'email'],
            ['id' => 1],
            true
        );

        $q = $tx->build();

        self::assertSame(
            'INSERT INTO `users` (`id`, `name`, `email`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `email` = VALUES(`email`)',
            $q->sql
        );
        self::assertSame([1, 'Alex', 'a@example.com'], $q->params);
        self::assertSame([0 => 1], $q->types);
        self::assertTrue($tx->isIdempotent());
    }
}
