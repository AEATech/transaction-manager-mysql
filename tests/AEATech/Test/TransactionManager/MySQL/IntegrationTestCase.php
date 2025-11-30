<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL;


use AEATech\TransactionManager\DoctrineAdapter\DbalConnectionAdapter;
use AEATech\TransactionManager\ExecutionPlanBuilder;
use AEATech\TransactionManager\MySQL\MySQLErrorClassifier;
use AEATech\TransactionManager\SystemSleeper;
use AEATech\TransactionManager\TransactionInterface;
use AEATech\TransactionManager\TransactionManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Result;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

abstract class IntegrationTestCase extends TestCase
{
    private static ?Connection $raw = null;
    private static ?DbalConnectionAdapter $adapter = null;
    private static ?TransactionManager $tm = null;

    /**
     * PHPUnit lifecycle: prepare shared instances.
     * Child classes can override but must call parent::setUpBeforeClass().
     */
    public static function setUpBeforeClass(): void
    {
        // Build shared connection/adapter/txManager once per test class
        $override = static::connectionParamsOverride();
        self::$raw = self::makeDbalConnection($override);
        self::$adapter = new DbalConnectionAdapter(self::$raw);
        self::$tm = new TransactionManager(
            new ExecutionPlanBuilder(),
            self::$adapter,
            new MySQLErrorClassifier(),
            new SystemSleeper(),
        );
    }

    /**
     * Clean the database before each test run.
     * Child classes may override to opt out or perform custom preparation, but should call parent::setUp().
     *
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetDatabase();
    }

    /**
     * Override in child to tweak connection options (e.g., port, timeouts).
     */
    protected static function connectionParamsOverride(): array
    {
        return [];
    }

    /**
     * Creates Doctrine DBAL connection for tests.
     * Do not call directly, use db() for a shared instance.
     */
    protected static function makeDbalConnection(array $overrideParams = []): Connection
    {
        $params = [
            'driver' => 'pdo_mysql',
            'host' => getenv('MYSQL_HOST'),
            'port' => $overrideParams['port'] ?? 3306,
            'dbname' => 'test',
            'user' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
            'driverOptions' => [
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        ];

        $params = array_replace_recursive($params, $overrideParams);

        return DriverManager::getConnection($params, new Configuration());
    }

    protected static function makeAdapter(array $overrideParams = []): DbalConnectionAdapter
    {
        return new DbalConnectionAdapter(self::makeDbalConnection($overrideParams));
    }

    protected static function makeTransactionManager(array $overrideParams = []): TransactionManager
    {
        return new TransactionManager(
            new ExecutionPlanBuilder(),
            self::makeAdapter($overrideParams),
            new MySQLErrorClassifier(),
            new SystemSleeper(),
        );
    }

    /**
     * PHPUnit lifecycle: close resources.
     */
    public static function tearDownAfterClass(): void
    {
        if (self::$raw) {
            try {
                self::$raw->close();
            } catch (Throwable) {
                // ignore
            }
        }
        self::$tm = null;
        self::$adapter = null;
        self::$raw = null;
    }

    /**
     * Universal DB reset: drop all tables in the current schema.
     * Uses FOREIGN_KEY_CHECKS to safely drop in any order.
     *
     * @throws Throwable
     */
    protected function resetDatabase(): void
    {
        $conn = self::db();

        $tables = $conn->fetchFirstColumn(
            "SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE()"
        );

        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($tables as $table) {
            $conn->executeStatement('DROP TABLE IF EXISTS `' . str_replace('`','``',$table).'`');
        }

        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    protected static function db(): Connection
    {
        if (!self::$raw) {
            $override = static::connectionParamsOverride();
            self::$raw = self::makeDbalConnection($override);
        }
        return self::$raw;
    }

    protected static function adapter(): DbalConnectionAdapter
    {
        if (!self::$adapter) {
            self::$adapter = new DbalConnectionAdapter(self::db());
        }
        return self::$adapter;
    }

    protected static function tm(): TransactionManager
    {
        if (!self::$tm) {
            self::$tm = new TransactionManager(
                new ExecutionPlanBuilder(),
                self::adapter(),
                new MySQLErrorClassifier(),
                new SystemSleeper(),
            );
        }

        return self::$tm;
    }

    /**
     * @throws Throwable
     */
    protected function runTransaction(
        TransactionInterface $transaction,
        TransactionInterface ...$nestedTransactions
    ): int {
        return self::tm()->run([$transaction, ...$nestedTransactions])->affectedRows;
    }
}
