<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL\Transaction;

use AEATech\Test\TransactionManager\MySQL\IntegrationTestCase;
use AEATech\TransactionManager\MySQL\MySQLIdentifierQuoter;
use AEATech\TransactionManager\MySQL\Transaction\DeleteWithLimitTransaction;
use AEATech\TransactionManager\Transaction\InsertTransaction;
use AEATech\TransactionManager\Transaction\Internal\InsertValuesBuilder;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

#[Group('integration')]
#[CoversClass(DeleteWithLimitTransaction::class)]
class DeleteWithLimitTransactionIntegrationTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::db()->executeStatement(
            <<<'SQL'
CREATE TABLE tm_delete_with_limit_test (
    id INT PRIMARY KEY,
    name VARCHAR(64) NOT NULL,
    age INT NOT NULL
) ENGINE=InnoDB
SQL
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function deleteWithLimitDeletesNoMoreThanLimit(): void
    {
        $seed = [
            ['id' => 1, 'name' => 'Alex', 'age' => 30],
            ['id' => 2, 'name' => 'Bob',  'age' => 25],
            ['id' => 3, 'name' => 'John', 'age' => 40],
            ['id' => 4, 'name' => 'Mary', 'age' => 28],
        ];

        $quoter = new MySQLIdentifierQuoter();

        $initTx = new InsertTransaction(new InsertValuesBuilder(), $quoter, 'tm_delete_with_limit_test', $seed);
        $this->runTransaction($initTx);

        // Require deletion of 3 ids, but limit to 2
        $idsToDelete = [1, 2, 3];

        $deleteTx = new DeleteWithLimitTransaction(
            $quoter,
            'tm_delete_with_limit_test',
            'id',
            ParameterType::INTEGER,
            $idsToDelete,
            2,
            true
        );

        $affected = $this->runTransaction($deleteTx);

        self::assertSame(2, $affected);

        $remaining = self::db()
            ->executeQuery('SELECT id, name, age FROM tm_delete_with_limit_test ORDER BY id')
            ->fetchAllAssociative();

        // Only 2 rows should be removed among 1,2,3; one of them should remain + id=4
        self::assertCount(2, $remaining);
        self::assertSame([
            ['id' => 3, 'name' => 'John', 'age' => 40],
            ['id' => 4, 'name' => 'Mary', 'age' => 28],
        ], $remaining);
    }
}
