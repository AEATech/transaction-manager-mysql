<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL;

use AEATech\TransactionManager\DoctrineAdapter\DbalConnectionAdapter;
use AEATech\TransactionManager\ExecutionPlanBuilder;
use AEATech\TransactionManager\MySQL\MySQLErrorClassifier;
use AEATech\TransactionManager\SystemSleeper;
use AEATech\TransactionManager\TransactionManager;
use AEATech\TransactionManager\MySQL\Transaction\InsertTransaction;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

#[Group('integration')]
#[CoversClass(InsertTransaction::class)]
class InsertTransactionIntegrationTest extends TestCase
{
    private static ?Connection $raw = null;

    /**
     * @throws Throwable
     */
    public static function setUpBeforeClass(): void
    {
        self::$raw = self::makeDbalConnection();

        self::$raw->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS tm_insert_test (
    id INT PRIMARY KEY,
    name VARCHAR(64) NOT NULL,
    age INT NOT NULL
) ENGINE=InnoDB
SQL
        );

        self::$raw->executeStatement('TRUNCATE tm_insert_test');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$raw) {
            try {
                self::$raw->executeStatement('DROP TABLE IF EXISTS tm_insert_test');
            } catch (Throwable) {
            }

            self::$raw->close();
            self::$raw = null;
        }
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function insertsMultipleRowsSuccessfully(): void
    {
        $adapter = new DbalConnectionAdapter(self::makeDbalConnection());

        $txManager = new TransactionManager(
            new ExecutionPlanBuilder(),
            $adapter,
            new MySQLErrorClassifier(),
            new SystemSleeper(),
        );

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

        $tx = new InsertTransaction('tm_insert_test', $rows, $types, isIdempotent: true);

        $result = $txManager->run($tx);

        self::assertSame(2, $result->affectedRows);

        $all = self::$raw
            ->executeQuery('SELECT id, name, age FROM tm_insert_test ORDER BY id')
            ->fetchAllAssociative();

        self::assertSame($expected, array_map(static function (array $row): array {
            // Cast numeric strings returned by some drivers to int
            $row['id'] = (int)$row['id'];
            $row['age'] = (int)$row['age'];
            return $row;
        }, $all));
    }

    private static function makeDbalConnection(): Connection
    {
        $params = [
            'driver' => 'pdo_mysql',
            'host' => getenv('MYSQL_HOST'),
            'port' => 3306,
            'dbname' => 'test',
            'user' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
            'driverOptions' => [
                PDO::ATTR_TIMEOUT => 2,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        ];

        return DriverManager::getConnection($params, new Configuration());
    }
}
