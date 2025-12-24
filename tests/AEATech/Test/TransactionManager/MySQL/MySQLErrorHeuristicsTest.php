<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL;

use AEATech\TransactionManager\MySQL\MySQLErrorHeuristics;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MySQLErrorHeuristics::class)]
class MySQLErrorHeuristicsTest extends TestCase
{
    private MySQLErrorHeuristics $heuristics;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->heuristics = new MySQLErrorHeuristics();
    }

    #[Test]
    public function isConnectionIssueBySqlStatePrefix(): void
    {
        self::assertTrue($this->heuristics->isConnectionIssue('08001', null, 'any'));
    }

    #[Test]
    public function isConnectionIssueByDriverCode(): void
    {
        self::assertTrue($this->heuristics->isConnectionIssue(null, 2006, 'server error'));
    }

    #[Test]
    public function isConnectionIssueByEmbeddedCodeWhenDriverCodeMissing(): void
    {
        $msg = 'SQLSTATE[HY000]: General error: 2002 Connection refused';

        self::assertTrue($this->heuristics->isConnectionIssue(null, null, $msg));
    }

    #[Test]
    #[DataProvider('connectionIssueMessageDataProvider')]
    public function isConnectionIssueByMessageSubstring(string $message): void
    {
        self::assertTrue($this->heuristics->isConnectionIssue(null, null, $message));
    }

    public static function connectionIssueMessageDataProvider(): array
    {
        return [
            'server has gone away' => ['MySQL server has gone away'],
            'connection reset' => ['Connection reset by peer during write'],
        ];
    }

    #[Test]
    public function isNotConnectionIssueWhenNoSignals(): void
    {
        $this->assertFalse($this->heuristics->isConnectionIssue('42000', 1064, 'syntax error near select'));
    }

    #[Test]
    public function isTransientIssueBySqlState(): void
    {
        self::assertTrue($this->heuristics->isTransientIssue('40001', null, 'serialization failure'));
    }

    #[Test]
    public function isTransientIssueByDriverCode(): void
    {
        self::assertTrue($this->heuristics->isTransientIssue(null, 1213, 'Deadlock found when trying to get lock'));
    }

    #[Test]
    public function isTransientIssueByEmbeddedCodeWhenDriverCodeMissing(): void
    {
        $msg = 'Error 1205: Lock wait timeout exceeded; try restarting transaction';
        self::assertTrue($this->heuristics->isTransientIssue(null, null, $msg));
    }

    #[Test]
    #[DataProvider('transientIssueMessageDataProvider')]
    public function isTransientIssueByMessageSubstring(string $message): void
    {
        self::assertTrue($this->heuristics->isTransientIssue(null, null, $message));
    }

    public static function transientIssueMessageDataProvider(): array
    {
        return [
            'deadlock' => ['deadlock found when trying to get lock'],
            'lock wait timeout' => ['Lock wait timeout exceeded; try restarting transaction'],
        ];
    }

    #[Test]
    public function isNotTransientIssueWhenNoSignals(): void
    {
        $this->assertFalse($this->heuristics->isTransientIssue('42000', 1064, 'syntax error near select'));
    }
}
