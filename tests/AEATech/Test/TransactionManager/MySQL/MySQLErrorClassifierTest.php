<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL;

use AEATech\TransactionManager\ErrorType;
use AEATech\TransactionManager\MySQL\MySQLErrorClassifier;
use Exception;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Throwable;

class MySQLErrorClassifierTest extends TestCase
{
    private MySQLErrorClassifier $classifier;

    protected function setUp(): void
    {
        $this->classifier = new MySQLErrorClassifier();
    }

    #[Test]
    public function deadlockByCode1213(): void
    {
        $e = new PDOException('Deadlock found when trying to get lock', 1213);
        $e->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock; try restarting transaction'];

        self::assertSame(ErrorType::Transient, $this->classifier->classify($e));
    }

    #[Test]
    public function lockWaitTimeout1205(): void
    {
        $e = new PDOException('Lock wait timeout exceeded; try restarting transaction', 1205);
        $e->errorInfo = ['HY000', 1205, 'Lock wait timeout exceeded; try restarting transaction'];

        self::assertSame(ErrorType::Transient, $this->classifier->classify($e));
    }

    #[Test]
    public function serializationFailure40001(): void
    {
        $e = new PDOException('Serialization failure');
        $e->errorInfo = ['40001', 0, 'Serialization failure'];

        self::assertSame(ErrorType::Transient, $this->classifier->classify($e));
    }

    #[Test]
    public function connectionLost2013(): void
    {
        $e = new PDOException('Lost connection to MySQL server during query', 2013);
        $e->errorInfo = ['HY000', 2013, 'Lost connection to MySQL server during query'];

        self::assertSame(ErrorType::Connection, $this->classifier->classify($e));
    }

    #[Test]
    public function serverHasGoneAway2006(): void
    {
        $e = new PDOException('MySQL server has gone away', 2006);
        $e->errorInfo = ['HY000', 2006, 'MySQL server has gone away'];

        self::assertSame(ErrorType::Connection, $this->classifier->classify($e));
    }

    #[Test]
    public function sqlState08Class(): void
    {
        $e = new PDOException('Communication link failure');
        $e->errorInfo = ['08S01', 0, 'Communication link failure'];

        self::assertSame(ErrorType::Connection, $this->classifier->classify($e));
    }

    #[Test]
    public function syntaxErrorIsFatal(): void
    {
        $e = new PDOException('You have an error in your SQL syntax', 1064);
        $e->errorInfo = ['42000', 1064, 'You have an error in your SQL syntax'];

        self::assertSame(ErrorType::Fatal, $this->classifier->classify($e));
    }

    #[Test]
    public function unknownExceptionIsFatal(): void
    {
        $e = new RuntimeException('Some logic error');

        self::assertSame(ErrorType::Fatal, $this->classifier->classify($e));
    }

    #[Test]
    public function overridesCanTreat1064AsTransient(): void
    {
        // Override transient driver codes to include 1064 (normally a syntax error, i.e., Fatal)
        $c = new MySQLErrorClassifier(
            transientErrorCodes: [1064]
        );

        $e = new PDOException('You have an error in your SQL syntax', 1064);
        $e->errorInfo = ['42000', 1064, 'You have an error in your SQL syntax'];

        self::assertSame(ErrorType::Transient, $c->classify($e));
    }

    #[Test]
    public function connectionByMessageHeuristicsWithoutCodes(): void
    {
        $cases = [
            'MySQL server has gone away',
            'Lost connection to MySQL server during query',
            'BROKEN PIPE',
            'no route to host',
            'timed out while reading from the connection',
        ];

        foreach ($cases as $msg) {
            $e = new PDOException($msg);
            // no errorInfo; should match by message
            self::assertSame(ErrorType::Connection, $this->classifier->classify($e), "Failed for: $msg");
        }
    }

    #[Test]
    public function transientByMessageHeuristicsWithoutCodes(): void
    {
        $cases = [
            'Deadlock found when trying to get lock',
            'LOCK WAIT TIMEOUT exceeded',
            'Try restarting transaction please',
        ];

        foreach ($cases as $msg) {
            $e = new PDOException($msg);
            self::assertSame(ErrorType::Transient, $this->classifier->classify($e), "Failed for: $msg");
        }
    }

    #[Test]
    public function sqlStateConnectionPrefixOverrideRemovesConnection(): void
    {
        // Default would classify 08S01 as Connection due to the prefix '08'
        $custom = new MySQLErrorClassifier(
            sqlstateConnectionPrefixes: ['99'] // change to non-matching prefix
        );

        $e = new PDOException('Communication link failure');
        $e->errorInfo = ['08S01', 0, 'Communication link failure'];

        // With override and no other signals, expect Fatal
        self::assertSame(ErrorType::Fatal, $custom->classify($e));
    }

    #[Test]
    public function customMessageOverridesWorkForConnectionAndTransient(): void
    {
        $customConn = new MySQLErrorClassifier(
            connectionMsgNeedles: ['network down']
        );
        $e1 = new PDOException('Network down on interface');
        self::assertSame(ErrorType::Connection, $customConn->classify($e1));

        $customTr = new MySQLErrorClassifier(
            transientMsgNeedles: ['temporary table busy']
        );
        $e2 = new PDOException('Temporary table busy, try later');
        self::assertSame(ErrorType::Transient, $customTr->classify($e2));
    }

    #[Test]
    public function customConnectionErrorCodesOverride(): void
    {
        // Treat 1064 (normally Fatal) as Connection by code override
        $c = new MySQLErrorClassifier(connectionErrorCodes: [1064]);
        $e = new PDOException('You have an error in your SQL syntax', 1064);
        $e->errorInfo = ['42000', 1064, 'You have an error in your SQL syntax'];
        self::assertSame(ErrorType::Connection, $c->classify($e));
    }

    private static function outerWithPrevious(Throwable $inner): RuntimeException
    {
        return new RuntimeException('wrapper', 0, $inner);
    }

    #[Test]
    public function exceptionChainTraversalFindsInnerCause(): void
    {
        $deadlock = new PDOException('Deadlock found', 1213);
        $deadlock->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock; try restarting transaction'];
        $outer = self::outerWithPrevious($deadlock);
        self::assertSame(ErrorType::Transient, $this->classifier->classify($outer));

        $gone = new PDOException('MySQL server has gone away', 2006);
        $gone->errorInfo = ['HY000', 2006, 'MySQL server has gone away'];
        $outer2 = self::outerWithPrevious($gone);
        self::assertSame(ErrorType::Connection, $this->classifier->classify($outer2));
    }

    #[Test]
    public function precedenceConnectionBeatsTransient(): void
    {
        $e = new PDOException('Lost connection to MySQL server during query', 2013);
        // include transient SQLSTATE too — connection should win
        $e->errorInfo = ['40001', 2013, 'Lost connection to MySQL server during query'];
        self::assertSame(ErrorType::Connection, $this->classifier->classify($e));
    }

    #[Test]
    public function fatalFallbackWhenNoSignals(): void
    {
        $e = new RuntimeException('benign message');

        self::assertSame(ErrorType::Fatal, $this->classifier->classify($e));
    }

    #[Test]
    public function extractErrorInfoFromGetCodeIntOnly(): void
    {
        // No errorInfo, but integer code 2013 should be treated as Connection
        $e = new RuntimeException('lost conn', 2013);

        self::assertSame(ErrorType::Connection, $this->classifier->classify($e));
    }

    #[Test]
    public function reflectiveGetSQLStateIsUsed(): void
    {
        $e = self::makeHasGetSQLState();

        self::assertSame(ErrorType::Transient, $this->classifier->classify($e));
    }

    #[Test]
    public function stringCodeDerivesSqlStateViaSubstring(): void
    {
        // Make a plain RuntimeException and force its internal 'code' to a string '40001X'
        $e = new RuntimeException('string code as sqlstate candidate', 0);

        $rp = new ReflectionProperty(Exception::class, 'code');
        $rp->setValue($e, '40001X');

        // Should derive SQLSTATE '40001' from the first 5 chars and classify as Transient
        self::assertSame(ErrorType::Transient, $this->classifier->classify($e));
    }

    #[Test]
    public function stringCodeTooShortDoesNotDeriveSqlState(): void
    {
        $e = new RuntimeException('short string code should not be used as sqlstate', 0);

        $rp = new ReflectionProperty(Exception::class, 'code');
        $rp->setValue($e, 'ERR'); // length < 5 should be ignored

        // No other signals present should fall back to Fatal
        self::assertSame(ErrorType::Fatal, $this->classifier->classify($e));
    }

    private static function makeHasGetSQLState(): Throwable
    {
        return new class('reflective sqlstate', 0) extends RuntimeException {
            public function __construct(string $message, int $code) { parent::__construct($message, $code); }
            public function getSQLState(): string { return '40001'; }
        };
    }
}
