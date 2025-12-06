<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL\Transaction;

use AEATech\Test\TransactionManager\MySQL\IntegrationTestCase;
use AEATech\TransactionManager\MySQL\MySQLIdentifierQuoter;
use AEATech\TransactionManager\MySQL\Transaction\InsertIgnoreTransaction;
use AEATech\TransactionManager\Transaction\Internal\InsertValuesBuilder;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

#[Group('integration')]
#[CoversClass(InsertIgnoreTransaction::class)]
class InsertIgnoreTransactionIntegrationTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::db()->executeStatement(
            <<<'SQL'
CREATE TABLE tm_insert_ignore_test (
    id INT PRIMARY KEY,
    name VARCHAR(64) NOT NULL
) ENGINE=InnoDB
SQL
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function insertIgnoreSkipsDuplicates(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Alex'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 1, 'name' => 'Alex'], // duplicate PK
        ];

        $types = [
            'id' => ParameterType::INTEGER,
            'name' => ParameterType::STRING,
        ];

        $tx = new InsertIgnoreTransaction(
            new InsertValuesBuilder(),
            new MySQLIdentifierQuoter(),
            'tm_insert_ignore_test',
            $rows,
            $types,
            false,
        );

        $affectedRows = $this->runTransaction($tx);

        // Two unique rows inserted, one duplicate ignored
        self::assertSame(2, $affectedRows);

        $actual = self::db()
            ->executeQuery('SELECT id, name FROM tm_insert_ignore_test ORDER BY id')
            ->fetchAllAssociative();

        $expected = [
            ['id' => 1, 'name' => 'Alex'],
            ['id' => 2, 'name' => 'Bob'],
        ];

        self::assertSame($expected, $actual);
    }
}
