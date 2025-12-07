<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL;

use AEATech\TransactionManager\MySQL\Transaction\DeleteWithLimitTransaction;
use AEATech\TransactionManager\MySQL\Transaction\DeleteWithLimitTransactionFactory;
use AEATech\TransactionManager\MySQL\Transaction\InsertIgnoreTransaction;
use AEATech\TransactionManager\MySQL\Transaction\InsertIgnoreTransactionFactory;
use AEATech\TransactionManager\MySQL\Transaction\InsertOnDuplicateKeyUpdateTransaction;
use AEATech\TransactionManager\MySQL\Transaction\InsertOnDuplicateKeyUpdateTransactionFactory;
use AEATech\TransactionManager\Transaction\InsertTransaction;
use AEATech\TransactionManager\Transaction\DeleteTransaction;
use AEATech\TransactionManager\Transaction\UpdateTransaction;
use AEATech\TransactionManager\Transaction\UpdateWhenThenTransaction;
use AEATech\TransactionManager\Transaction\InsertTransactionFactory;
use AEATech\TransactionManager\Transaction\SqlTransaction;
use AEATech\TransactionManager\Transaction\DeleteTransactionFactory;
use AEATech\TransactionManager\Transaction\UpdateTransactionFactory;
use AEATech\TransactionManager\Transaction\UpdateWhenThenTransactionFactory;
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
    private InsertIgnoreTransactionFactory&m\MockInterface $insertIgnoreFactory;
    private InsertOnDuplicateKeyUpdateTransactionFactory&m\MockInterface $upsertFactory;
    private DeleteTransactionFactory&m\MockInterface $deleteFactory;
    private DeleteWithLimitTransactionFactory&m\MockInterface $deleteWithLimitFactory;
    private UpdateTransactionFactory&m\MockInterface $updateFactory;
    private UpdateWhenThenTransactionFactory&m\MockInterface $updateWhenThenFactory;
    private TransactionsFactory $transactionsFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertFactory = m::mock(InsertTransactionFactory::class);
        $this->insertIgnoreFactory = m::mock(InsertIgnoreTransactionFactory::class);
        $this->upsertFactory = m::mock(InsertOnDuplicateKeyUpdateTransactionFactory::class);
        $this->deleteFactory = m::mock(DeleteTransactionFactory::class);
        $this->deleteWithLimitFactory = m::mock(DeleteWithLimitTransactionFactory::class);
        $this->updateFactory = m::mock(UpdateTransactionFactory::class);
        $this->updateWhenThenFactory = m::mock(UpdateWhenThenTransactionFactory::class);

        $this->transactionsFactory = new TransactionsFactory(
            $this->insertFactory,
            $this->insertIgnoreFactory,
            $this->upsertFactory,
            $this->deleteFactory,
            $this->deleteWithLimitFactory,
            $this->updateFactory,
            $this->updateWhenThenFactory,
        );
    }

    #[Test]
    public function createInsert(): void
    {
        $rows = [['id' => 1, 'name' => 'A']];
        $tx = m::mock(InsertTransaction::class);

        $this->insertFactory->shouldReceive('factory')
            ->once()
            ->with('users', $rows, ['id' => 1], true)
            ->andReturn($tx);

        $result = $this->transactionsFactory->createInsert('users', $rows, ['id' => 1], true);

        self::assertSame($tx, $result);
    }

    #[Test]
    public function createInsertIgnore(): void
    {
        $rows = [['id' => 2, 'name' => 'B']];
        $tx = m::mock(InsertIgnoreTransaction::class);

        $this->insertIgnoreFactory->shouldReceive('factory')
            ->once()
            ->with('t', $rows, [], false)
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

    #[Test]
    public function createDeleteWithLimit(): void
    {
        $tx = m::mock(DeleteWithLimitTransaction::class);

        $this->deleteWithLimitFactory->shouldReceive('factory')
            ->once()
            ->with('logs', 'id', 1, [10, 11, 12], 2, true)
            ->andReturn($tx);

        $result = $this->transactionsFactory->createDeleteWithLimit(
            'logs',
            'id',
            1,
            [10, 11, 12],
            2
        );

        self::assertSame($tx, $result);
    }

    #[Test]
    public function createDelete(): void
    {
        $tx = m::mock(DeleteTransaction::class);

        $this->deleteFactory->shouldReceive('factory')
            ->once()
            ->with('users', 'id', 1, [1, 2, 3], true)
            ->andReturn($tx);

        $result = $this->transactionsFactory->createDelete(
            'users',
            'id',
            1,
            [1, 2, 3]
        );

        self::assertSame($tx, $result);
    }

    #[Test]
    public function createUpdate(): void
    {
        $tx = m::mock(UpdateTransaction::class);

        $identifiers = [10, 11];
        $values = ['status' => 'active', 'score' => 100];
        $types = ['status' => 'string', 'score' => 1];

        $this->updateFactory->shouldReceive('factory')
            ->once()
            ->with('profiles', 'id', 1, $identifiers, $values, $types, false)
            ->andReturn($tx);

        $result = $this->transactionsFactory->createUpdate(
            'profiles',
            'id',
            1,
            $identifiers,
            $values,
            $types,
            false
        );

        self::assertSame($tx, $result);
    }

    #[Test]
    public function createUpdateWhenThen(): void
    {
        $tx = m::mock(UpdateWhenThenTransaction::class);

        $rows = [
            ['id' => 1, 'status' => 'new',   'score' => 10],
            ['id' => 2, 'status' => 'ready', 'score' => 20],
        ];
        $updateColumns = ['status', 'score'];
        $updateTypes = ['status' => 'string', 'score' => 1];

        $this->updateWhenThenFactory->shouldReceive('factory')
            ->once()
            ->with('tasks', $rows, 'id', 1, $updateColumns, $updateTypes, true)
            ->andReturn($tx);

        $result = $this->transactionsFactory->createUpdateWhenThen(
            'tasks',
            $rows,
            'id',
            1,
            $updateColumns,
            $updateTypes
        );

        self::assertSame($tx, $result);
    }
}
