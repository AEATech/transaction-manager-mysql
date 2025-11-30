<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL\Transaction;

use AEATech\Test\TransactionManager\MySQL\IntegrationTestCase;
use AEATech\TransactionManager\MySQL\Internal\InsertValuesBuilder;
use AEATech\TransactionManager\MySQL\Transaction\InsertTransaction;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

#[Group('integration')]
#[CoversClass(InsertTransaction::class)]
class InsertTransactionIntegrationTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::db()->executeStatement(
            <<<'SQL'
CREATE TABLE tm_insert_test (
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
    public function insertsMultipleRowsSuccessfully(): void
    {
        $expected = [
            [
                'id'   => 1,
                'name' => 'Alex',
                'age'  => 30
            ],
            [
                'id'   => 2,
                'name' => 'Bob',
                'age'  => 25
            ],
        ];

        $rows = $expected;

        $types = [
            'id' => ParameterType::INTEGER,
            'name' => ParameterType::STRING,
            'age' => ParameterType::INTEGER,
        ];

        $tx = new InsertTransaction(new InsertValuesBuilder(), 'tm_insert_test', $rows, $types);

        $affectedRows = $this->runTransaction($tx);

        self::assertSame(2, $affectedRows);

        $actual = self::db()
            ->executeQuery('SELECT id, name, age FROM tm_insert_test ORDER BY id')
            ->fetchAllAssociative();

        self::assertSame($expected, $actual);
    }
}
