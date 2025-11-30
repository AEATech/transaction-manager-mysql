<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL;

use AEATech\TransactionManager\DoctrineAdapter\DbalConnectionAdapter;
use AEATech\TransactionManager\ErrorType;
use AEATech\TransactionManager\MySQL\MySQLErrorClassifier;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Integration tests that verify MySQLErrorClassifier against a real MySQL server:
 * - lock wait timeouts
 * - connection failures
 * - server gone away
 * - real InnoDB deadlocks (both SQLSTATE 40001 and true concurrent deadlock).
 */
#[Group('integration')]
#[CoversClass(MySQLErrorClassifier::class)]
class MySQLErrorClassifierIntegrationTest extends TestCase
{
    private static ?Connection $raw = null;

    private MySQLErrorClassifier $classifier;

    protected function setUp(): void
    {
        $this->classifier = new MySQLErrorClassifier();
    }

    /**
     * @throws Exception
     */
    public static function setUpBeforeClass(): void
    {
        self::$raw = self::makeDbalConnection();

        self::$raw->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS tm_lock_test (
    id INT PRIMARY KEY,
    val INT NOT NULL
) ENGINE=InnoDB
SQL
);
        self::$raw->executeStatement('DELETE FROM tm_lock_test');
        self::$raw->executeStatement('INSERT INTO tm_lock_test (id, val) VALUES (1, 0), (2, 0)');

        // Sync helper table for inter-process signaling in a deadlock test
        self::$raw->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS tm_sync (
    k VARCHAR(32) PRIMARY KEY
) ENGINE=InnoDB
SQL
);
        self::$raw->executeStatement('DELETE FROM tm_sync');

        self::$raw->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS tm_unique_test (
    id INT PRIMARY KEY,
    email VARCHAR(255) UNIQUE
) ENGINE=InnoDB
SQL
        );

        self::$raw->executeStatement('TRUNCATE tm_unique_test');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$raw) {
            try {
                self::$raw->executeStatement('DROP TABLE IF EXISTS tm_lock_test');
            } catch (Throwable) {
            }

            try {
                self::$raw->executeStatement('DROP TABLE IF EXISTS tm_sync');
            } catch (Throwable) {
            }

            try {
                self::$raw->executeStatement('DROP TABLE IF EXISTS tm_unique_test');
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
    public function lockWaitTimeoutIsTransient(): void
    {
        $a1 = self::makeAdapter();
        $a2 = self::makeAdapter();

        // Keep a lock on row 1 via a2
        $a2->beginTransaction();
        $a2->executeStatement('UPDATE tm_lock_test SET val = val + 1 WHERE id = 1');

        // Minimize wait timeout for victim session
        $a1->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
        $a1->beginTransaction();

        $thrown = null;

        try {
            $a1->executeStatement('UPDATE tm_lock_test SET val = val + 1 WHERE id = 1');
        } catch (Throwable $e) {
            $thrown = $e;
        } finally {
            try {
                $a1->rollBack();
            } catch (Throwable) {
            }

            try {
                $a2->rollBack();
            } catch (Throwable) {
            }
        }

        self::assertInstanceOf(Throwable::class, $thrown, 'Expected a lock wait timeout error');
        self::assertSame(ErrorType::Transient, $this->classifier->classify($thrown));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function connectionErrorOnInvalidPortIsConnection(): void
    {
        $adapter = self::makeAdapter(['port' => 3307, 'connect_timeout' => 1]);

        $caught = null;

        try {
            $adapter->beginTransaction();
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertInstanceOf(Throwable::class, $caught, 'Expected connection failure');
        self::assertSame(ErrorType::Connection, $this->classifier->classify($caught));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function serverHasGoneAwayIsConnection(): void
    {
        $adapter = self::makeAdapter();

        // Shrink timeouts so that idle connection is dropped quickly
        $adapter->executeStatement('SET SESSION wait_timeout = 1');
        $adapter->executeStatement('SET SESSION interactive_timeout = 1');

        $adapter->beginTransaction();
        // Sleep beyond wait_timeout to force the server to drop the connection
        sleep(2);

        $thrown = null;

        try {
            // Any statement should now fail with "server has gone away"/lost connection
            // php-8.2 and 8.3 trigger a php warning that's why error suppression is necessary
            @$adapter->executeStatement('SELECT 1');
        } catch (Throwable $e) {
            $thrown = $e;
        } finally {
            try {
                $adapter->rollBack();
            } catch (Throwable) {
            }

            $adapter->close();
        }

        self::assertInstanceOf(Throwable::class, $thrown, 'Expected server gone away');
        self::assertSame(ErrorType::Connection, $this->classifier->classify($thrown));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function deadlockTransientViaSqlState40001Signal(): void
    {
        // MySQL allows raising SQLSTATE 40001 explicitly; classifier must treat it as Transient
        $adapter = self::makeAdapter();

        $caught = null;
        try {
            $adapter->executeStatement(
                "SIGNAL SQLSTATE '40001' SET MESSAGE_TEXT = 'Deadlock found when trying to get lock';"
            );
        } catch (Throwable $e) {
            $caught = $e;
        }

        self::assertInstanceOf(Throwable::class, $caught, 'Expected SQLSTATE 40001 error');
        self::assertSame(ErrorType::Transient, $this->classifier->classify($caught));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function deadlockRealIsTransientWhenConcurrentTransactions(): void
    {
        // Parent: begin and lock id=1
        $parentAdapter = self::makeAdapter();
        $parentThrown = null;

        // Build a child inline PHP script to run in a subprocess
        $childCode = <<<'PHPCHILD'
require 'vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Configuration;

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

$conn = DriverManager::getConnection($params, new Configuration());
$payload = null;

try {
    $conn->beginTransaction();
    $conn->executeStatement('UPDATE tm_lock_test SET val = val + 1 WHERE id = 2');

    try {
       $conn->executeStatement("INSERT IGNORE INTO tm_sync (k) VALUES ('child_ready')");
    } catch (\Throwable $ex) {
    }
    
    usleep(200000);
    
    // Conflicting update expected to deadlock
    $conn->executeStatement('UPDATE tm_lock_test SET val = val + 1 WHERE id = 1');
} catch (\Throwable $e) {
    $payload = [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'sqlstate' => null,
        'driverCode' => null,
    ];

    if ($e instanceof PDOException && is_array($e->errorInfo ?? null)) {
        $payload['sqlstate'] = $e->errorInfo[0] ?? null;
        $payload['driverCode'] = isset($e->errorInfo[1]) && is_numeric($e->errorInfo[1]) ? (int)$e->errorInfo[1] : null;
    }
} finally {
    try {
       $conn->rollBack();
    } catch (\Throwable $ex) {
    }
}
echo json_encode($payload);
PHPCHILD;

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $cmd = 'php -r ' . escapeshellarg($childCode);
        $proc = proc_open($cmd, $descriptorSpec, $pipes, getcwd());

        if (!is_resource($proc)) {
            $this->markTestSkipped('Failed to start child php process');
        }

        fclose($pipes[0]); // no stdin

        try {
            $parentAdapter->beginTransaction();

            $parentAdapter->executeStatement(<<<'SQL'
UPDATE tm_lock_test SET val = val + 1 WHERE id = 1
SQL
);

            // Wait for child_ready
            $start = microtime(true);

            do {
                $ready = (int)self::$raw->executeQuery(<<<SQL
SELECT COUNT(*) c FROM tm_sync WHERE k = 'child_ready'
SQL
)->fetchOne();

                if ($ready > 0) {
                    break;
                }

                usleep(50_000);
            } while (microtime(true) - $start < 5.0);

            // Attempt conflicting update; one side should deadlock
            try {
                $parentAdapter->executeStatement(<<<'SQL'
UPDATE tm_lock_test SET val = val + 1 WHERE id = 2
SQL
);
            } catch (Throwable $e) {
                $parentThrown = $e;
            }
        } finally {
            try {
                $parentAdapter->rollBack();
            } catch (Throwable) {
            }
        }

        // Read child stdout/stderr and close the process
        $childOut = stream_get_contents($pipes[1]);
        $childErr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        // Build candidate Throwable
        $candidate = $parentThrown;
        $payload = null;

        if (is_string($childOut) && $childOut !== '') {
            /** @noinspection JsonEncodingApiUsageInspection */
            $decoded = json_decode($childOut, true);

            if (is_array($decoded) && ($decoded['message'] ?? null)) {
                $payload = $decoded;
            }
        }

        if ($candidate === null && is_array($payload)) {
            $code = $payload['driverCode'] ?? ($payload['code'] ?? 0);
            $intCode = is_int($code) ? $code : 0;
            $pe = new PDOException($payload['message'] ?? 'deadlock', $intCode);
            $sqlstate = $payload['sqlstate'] ?? null;
            $driverCode = is_int($payload['driverCode'] ?? null) ? (int)$payload['driverCode'] : null;
            $pe->errorInfo = [$sqlstate, $driverCode, $payload['message'] ?? null];
            $candidate = $pe;
        }

        // If neither parent nor child produced an error, surface diagnostics
        if ($candidate === null) {
            $this->fail('Expected a real deadlock; childErr='.$childErr.'; childOut='.$childOut);
        }

        self::assertSame(ErrorType::Transient, $this->classifier->classify($candidate));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function duplicateKeyViolationIsFatal(): void
    {
        $adapter = self::makeAdapter();

        $adapter->executeStatement(<<<SQL
INSERT INTO tm_unique_test (id, email) VALUES (1, 'a@example.com')
SQL
);

        $thrown = null;

        try {
            $adapter->executeStatement(<<<SQL
INSERT INTO tm_unique_test (id, email) VALUES (2, 'a@example.com')
SQL
);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        self::assertInstanceOf(Throwable::class, $thrown);
        self::assertSame(ErrorType::Fatal, $this->classifier->classify($thrown));
    }

    private static function makeDbalConnection(array $overrideParams = []): Connection
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
                PDO::ATTR_TIMEOUT => $overrideParams['connect_timeout'] ?? 2,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        ];

        return DriverManager::getConnection($params, new Configuration());
    }

    private static function makeAdapter(array $overrideParams = []): DbalConnectionAdapter
    {
        return new DbalConnectionAdapter(self::makeDbalConnection($overrideParams));
    }
}
