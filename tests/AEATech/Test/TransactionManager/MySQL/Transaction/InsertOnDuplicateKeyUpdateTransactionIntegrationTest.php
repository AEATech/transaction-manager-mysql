<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL\Transaction;

use AEATech\Test\TransactionManager\MySQL\IntegrationTestCase;
use AEATech\TransactionManager\MySQL\Internal\InsertValuesBuilder;
use AEATech\TransactionManager\MySQL\Transaction\InsertOnDuplicateKeyUpdateTransaction;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

#[Group('integration')]
#[CoversClass(InsertOnDuplicateKeyUpdateTransaction::class)]
class InsertOnDuplicateKeyUpdateTransactionIntegrationTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::db()->executeStatement(
            <<<'SQL'
CREATE TABLE tm_upsert_test (
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
    public function upsertsRowsByPrimaryKey(): void
    {
        // Seed an initial row that will be updated by the upsert
        self::db()->executeStatement(
            'INSERT INTO tm_upsert_test (id, name, age) VALUES (1, "Alex", 30)'
        );

        // Row with existing PK id=1 will be updated; id=2 will be inserted
        $rows = [
            ['id' => 1, 'name' => 'Alexey', 'age' => 31],
            ['id' => 2, 'name' => 'Bob',    'age' => 25],
        ];

        $types = [
            'id' => ParameterType::INTEGER,
            'name' => ParameterType::STRING,
            'age' => ParameterType::INTEGER,
        ];

        $tx = new InsertOnDuplicateKeyUpdateTransaction(
            new InsertValuesBuilder(),
            'tm_upsert_test',
            $rows,
            ['name', 'age'],
            $types,
        );

        $affectedRows = $this->runTransaction($tx);

        // One INSERT (1) + one UPDATE that changes values (2) => total 3 affected rows
        self::assertSame(3, $affectedRows);

        $actual = self::db()
            ->executeQuery('SELECT id, name, age FROM tm_upsert_test ORDER BY id')
            ->fetchAllAssociative();

        $expected = [
            ['id' => 1, 'name' => 'Alexey', 'age' => 31],
            ['id' => 2, 'name' => 'Bob',    'age' => 25],
        ];

        self::assertSame($expected, $actual);
    }
}
