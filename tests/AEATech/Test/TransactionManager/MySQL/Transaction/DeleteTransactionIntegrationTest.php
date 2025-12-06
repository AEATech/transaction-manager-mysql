<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL\Transaction;

use AEATech\Test\TransactionManager\MySQL\IntegrationTestCase;
use AEATech\TransactionManager\MySQL\MySQLIdentifierQuoter;
use AEATech\TransactionManager\Transaction\DeleteTransaction;
use AEATech\TransactionManager\Transaction\InsertTransaction;
use AEATech\TransactionManager\Transaction\Internal\InsertValuesBuilder;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

#[Group('integration')]
#[CoversClass(DeleteTransaction::class)]
class DeleteTransactionIntegrationTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::db()->executeStatement(
            <<<'SQL'
CREATE TABLE tm_delete_test (
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
    public function deleteSuccessfully(): void
    {
        /**
         * Init state
         */
        $initState = [
            ['id' => 1, 'name' => 'Alex', 'age' => 30],
            ['id' => 2, 'name' => 'Bob',  'age' => 25],
            ['id' => 3, 'name' => 'John', 'age' => 40],
            ['id' => 4, 'name' => 'Mary', 'age' => 28],
        ];

        $mySQLIdentifierQuoter = new MySQLIdentifierQuoter();

        $initTransaction = new InsertTransaction(
            new InsertValuesBuilder(),
            $mySQLIdentifierQuoter,
            'tm_delete_test',
            $initState
        );

        $affectedRows = $this->runTransaction($initTransaction);

        self::assertSame(count($initState), $affectedRows);

        /**
         * Test delete
         */
        $identifiersForDelete = [];
        $expected = [];
        foreach ($initState as $index => $item) {
            if ($index % 2 === 0) {
                $identifiersForDelete[] = $item['id'];
            } else {
                $expected[] = $item;
            }
        }

        $deleteTransaction = new DeleteTransaction(
            $mySQLIdentifierQuoter,
            'tm_delete_test',
            'id',
            ParameterType::INTEGER,
            $identifiersForDelete
        );

        $affectedRows = $this->runTransaction($deleteTransaction);

        self::assertSame(count($identifiersForDelete), $affectedRows);

        $actual = self::db()
            ->executeQuery('SELECT id, name, age FROM tm_delete_test ORDER BY id')
            ->fetchAllAssociative();

        self::assertSame($expected, $actual);
    }
}
