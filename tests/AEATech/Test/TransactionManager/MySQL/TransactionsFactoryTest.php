<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL;

use AEATech\TransactionManager\MySQL\Transaction\InsertMode;
use AEATech\TransactionManager\MySQL\Transaction\InsertOnDuplicateKeyUpdateTransaction;
use AEATech\TransactionManager\MySQL\Transaction\InsertOnDuplicateKeyUpdateTransactionFactory;
use AEATech\TransactionManager\MySQL\Transaction\InsertTransaction;
use AEATech\TransactionManager\MySQL\Transaction\InsertTransactionFactory;
use AEATech\TransactionManager\MySQL\Transaction\SqlTransaction;
use AEATech\TransactionManager\MySQL\TransactionsFactory;
use Mockery as m;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransactionsFactory::class)]
class TransactionsFactoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private InsertTransactionFactory&m\MockInterface $insertFactory;
    private InsertOnDuplicateKeyUpdateTransactionFactory&m\MockInterface $upsertFactory;
    private TransactionsFactory $transactionsFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertFactory = m::mock(InsertTransactionFactory::class);
        $this->upsertFactory = m::mock(InsertOnDuplicateKeyUpdateTransactionFactory::class);
        $this->transactionsFactory = new TransactionsFactory($this->insertFactory, $this->upsertFactory);
    }

    #[Test]
    public function createInsert(): void
    {
        $rows = [['id' => 1, 'name' => 'A']];
        $tx = m::mock(InsertTransaction::class);

        $this->insertFactory->shouldReceive('factory')
            ->once()
            ->with('users', $rows, ['id' => 1], InsertMode::Regular, true)
            ->andReturn($tx);

        $result = $this->transactionsFactory->createInsert('users', $rows, ['id' => 1], true);

        self::assertSame($tx, $result);
    }

    #[Test]
    public function createInsertIgnore(): void
    {
        $rows = [['id' => 2, 'name' => 'B']];
        $tx = m::mock(InsertTransaction::class);

        $this->insertFactory->shouldReceive('factory')
            ->once()
            ->with('t', $rows, [], InsertMode::Ignore, false)
            ->andReturn($tx);

        $result = $this->transactionsFactory->createInsertIgnore('t', $rows);

        self::assertSame($tx, $result);
    }

    #[Test]
    public function createInsertOnDuplicateKeyUpdate(): void
    {
        $rows = [['id' => 3, 'email' => 'x@example.com', 'name' => 'X']];
        $updateColumns = ['email', 'name'];
        $tx = m::mock(InsertOnDuplicateKeyUpdateTransaction::class);

        $this->upsertFactory->shouldReceive('factory')
            ->once()
            ->with('contacts', $rows, $updateColumns, ['id' => 1], true)
            ->andReturn($tx);

        $result = $this->transactionsFactory->createInsertOnDuplicateKeyUpdate(
            'contacts',
            $rows,
            $updateColumns,
            ['id' => 1],
            true
        );

        self::assertSame($tx, $result);
    }

    #[Test]
    public function createSql(): void
    {
        $sql = 's...';
        $params = ['p...'];
        $types = ['t...'];

        $expected = new SqlTransaction($sql, $params, $types);

        $sqlTransaction = $this->transactionsFactory->createSql($sql, $params, $types);

        self::assertEquals($expected, $sqlTransaction);
    }
}
