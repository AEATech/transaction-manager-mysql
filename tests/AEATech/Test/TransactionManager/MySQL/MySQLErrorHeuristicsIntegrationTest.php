<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL;

use AEATech\TransactionManager\ErrorType;
use AEATech\TransactionManager\GenericErrorClassifier;
use AEATech\TransactionManager\MySQL\MySQLErrorHeuristics;
use AEATech\TransactionManager\TxOptions;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

/**
 * Integration tests that verify GenericErrorClassifier against a real MySQL server:
 * - lock wait timeouts
 * - connection failures
 * - server gone away
 * - real InnoDB deadlocks (both SQLSTATE 40001 and true concurrent deadlock).
 */
#[Group('integration')]
#[CoversClass(GenericErrorClassifier::class)]
#[CoversClass(MySQLErrorHeuristics::class)]
class MySQLErrorHeuristicsIntegrationTest extends IntegrationTestCase
{
    private GenericErrorClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classifier = new GenericErrorClassifier(new MySQLErrorHeuristics());

        self::db()->executeStatement(
            <<<'SQL'
CREATE TABLE tm_lock_test (
    id INT PRIMARY KEY,
    val INT NOT NULL
) ENGINE=InnoDB
SQL
        );

        self::db()->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS tm_sync (
    k VARCHAR(32) PRIMARY KEY
) ENGINE=InnoDB
SQL
        );
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function lockWaitTimeoutIsTransient(): void
    {
        self::db()->executeStatement(<<<'SQL'
INSERT INTO tm_lock_test (id, val) VALUES (1, 0), (2, 0)
SQL
        );

        $adapter1 = self::makeAdapter(self::makeDbalConnection());
        $adapter2 = self::makeAdapter(self::makeDbalConnection());

        // Keep a lock on row 1 via adapter2
        $adapter2->beginTransactionWithOptions(new TxOptions());

        $adapter2->executeStatement(<<<'SQL'
UPDATE tm_lock_test SET val = val + 1 WHERE id = 1
SQL
);

        // Minimize wait timeout for victim session
        $adapter1->executeStatement(<<<'SQL'
SET SESSION innodb_lock_wait_timeout = 1
SQL
);
        $adapter1->beginTransactionWithOptions(new TxOptions());

        $thrown = null;

        try {
            $adapter1->executeStatement(<<<'SQL'
UPDATE tm_lock_test SET val = val + 1 WHERE id = 1
SQL
);
        } catch (Throwable $e) {
            $thrown = $e;
        } finally {
            try {
                $adapter1->rollBack();
            } catch (Throwable) {
            }

            try {
                $adapter2->rollBack();
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
        $adapter = self::makeAdapter(self::makeDbalConnection(['port' => 3307, 'connect_timeout' => 1]));

        $caught = null;

        try {
            $adapter->beginTransactionWithOptions(new TxOptions());
        } catch (Throwable $e) {
            $caught = $e;

            try {
                $adapter->rollBack();
            } catch (Throwable) {
            }
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
        // Shrink timeouts so that idle connection is dropped quickly
        self::adapter()->executeStatement('SET SESSION wait_timeout = 1');
        self::adapter()->executeStatement('SET SESSION interactive_timeout = 1');

        self::adapter()->beginTransactionWithOptions(new TxOptions());

        // Sleep beyond wait_timeout to force the server to drop the connection
        sleep(2);

        $thrown = null;

        try {
            // Any statement should now fail with "server has gone away"/lost connection
            // php-8.2 and 8.3 trigger a php warning that's why error suppression is necessary
            @self::adapter()->executeStatement('SELECT 1');
        } catch (Throwable $e) {
            $thrown = $e;
        } finally {
            try {
                self::adapter()->rollBack();
            } catch (Throwable) {
            }
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
        $caught = null;

        try {
            self::adapter()->executeStatement(
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
        $parentThrown = null;

        self::db()->executeStatement(<<<'SQL'
INSERT INTO tm_lock_test (id, val) VALUES (1, 0), (2, 0)
SQL
        );

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

        self::adapter()->beginTransactionWithOptions(new TxOptions());

        self::adapter()->executeStatement(<<<'SQL'
UPDATE tm_lock_test SET val = val + 1 WHERE id = 1
SQL
);

        // Wait for child_ready
        $start = microtime(true);

        do {
                $ready = (int)self::db()->executeQuery(<<<SQL
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
            self::adapter()->executeStatement(<<<'SQL'
UPDATE tm_lock_test SET val = val + 1 WHERE id = 2
SQL
);
        } catch (Throwable $e) {
            $parentThrown = $e;

            try {
                self::adapter()->rollBack();
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
        self::db()->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS tm_unique_test (
    id INT PRIMARY KEY,
    email VARCHAR(255) UNIQUE
) ENGINE=InnoDB
SQL
        );

        self::db()->executeStatement(<<<SQL
INSERT INTO tm_unique_test (id, email) VALUES (1, 'a@example.com')
SQL
);

        $thrown = null;

        try {
            self::adapter()->executeStatement(<<<SQL
INSERT INTO tm_unique_test (id, email) VALUES (2, 'a@example.com')
SQL
);
        } catch (Throwable $e) {
            $thrown = $e;
        }

        self::assertInstanceOf(Throwable::class, $thrown);
        self::assertSame(ErrorType::Fatal, $this->classifier->classify($thrown));
    }
}
