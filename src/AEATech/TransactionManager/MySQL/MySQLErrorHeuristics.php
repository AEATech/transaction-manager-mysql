<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL;

use AEATech\TransactionManager\DatabaseErrorHeuristicsInterface;

class MySQLErrorHeuristics implements DatabaseErrorHeuristicsInterface
{
    public const DEFAULT_SQLSTATE_CONNECTION_PREFIXES = ['08'];
    public const DEFAULT_CONNECTION_ERROR_CODES = [2002, 2006, 2013, 4031];
    public const DEFAULT_CONNECTION_MESSAGE_SUBSTRINGS = [
        'server has gone away',
        // broaden common variants observed across PHP/MySQL/DBAL versions
        'has gone away',
        'lost connection to mysql server',
        'while sending query packet',
        'error while sending query',
        'packets out of order',
        'reading from the connection',
        'reading from the server',
        'writing to the server',
        'broken pipe',
        'connection reset by peer',
        'connection refused',
        'no route to host',
        'server closed the connection',
        'disconnected by the server because of inactivity',
        'client was disconnected by the server',
    ];

    public const DEFAULT_TRANSIENT_SQL_STATES = ['40001']; // serialization failure
    public const DEFAULT_TRANSIENT_ERROR_CODES = [1205, 1213]; // lock wait timeout, deadlock
    public const DEFAULT_TRANSIENT_MESSAGE_SUBSTRINGS = [
        'deadlock found',
        'lock wait timeout',
        'try restarting transaction',
    ];

    /**
     * @var string[]
     */
    private array $sqlstateConnectionPrefixes;

    /**
     * @var int[]
     */
    private array $connectionErrorCodes;

    /**
     * @var string[]
     */
    private array $connectionMsgNeedles;

    /**
     * @var string[]
     */
    private array $transientSqlStates;

    /**
     * @var int[]
     */
    private array $transientErrorCodes;

    /**
     * @var string[]
     */
    private array $transientMsgNeedles;

    /**
     * @param string[]|null $sqlstateConnectionPrefixes
     * @param int[]|null    $connectionErrorCodes
     * @param string[]|null $connectionMsgNeedles
     * @param string[]|null $transientSqlStates
     * @param int[]|null    $transientErrorCodes
     * @param string[]|null $transientMsgNeedles
     */
    public function __construct(
        ?array $sqlstateConnectionPrefixes = null,
        ?array $connectionErrorCodes = null,
        ?array $connectionMsgNeedles = null,
        ?array $transientSqlStates = null,
        ?array $transientErrorCodes = null,
        ?array $transientMsgNeedles = null,
    ) {
        $this->sqlstateConnectionPrefixes = $sqlstateConnectionPrefixes ?? self::DEFAULT_SQLSTATE_CONNECTION_PREFIXES;
        $this->connectionErrorCodes = $connectionErrorCodes ?? self::DEFAULT_CONNECTION_ERROR_CODES;
        $this->connectionMsgNeedles = $connectionMsgNeedles ?? self::DEFAULT_CONNECTION_MESSAGE_SUBSTRINGS;
        $this->transientSqlStates = $transientSqlStates ?? self::DEFAULT_TRANSIENT_SQL_STATES;
        $this->transientErrorCodes = $transientErrorCodes ?? self::DEFAULT_TRANSIENT_ERROR_CODES;
        $this->transientMsgNeedles = $transientMsgNeedles ?? self::DEFAULT_TRANSIENT_MESSAGE_SUBSTRINGS;
    }

    public function isConnectionIssue(?string $sqlState, ?int $driverCode, string $message): bool
    {
        $msg = strtolower($message);

        // SQLSTATE class 08xxx (by default) indicates a connection exception
        if ($sqlState !== null) {
            foreach ($this->sqlstateConnectionPrefixes as $prefix) {
                if ($prefix !== '' && str_starts_with($sqlState, $prefix)) {
                    return true;
                }
            }
        }

        // MySQL client/server connection error codes (configurable)
        if (in_array($driverCode, $this->connectionErrorCodes, true)) {
            return true;
        }

        // If driver code is unavailable, try to infer by looking for known codes inside the message
        if ($driverCode === null && $this->containsAnyErrorCode($msg, $this->connectionErrorCodes)) {
            return true;
        }

        // Heuristic by message substrings
        foreach ($this->connectionMsgNeedles as $n) {
            if (str_contains($msg, $n)) {
                return true;
            }
        }

        return false;
    }

    public function isTransientIssue(?string $sqlState, ?int $driverCode, string $message): bool
    {
        $msg = strtolower($message);

        // Standard SQLSTATE for serialization failure
        if ($sqlState !== null && in_array($sqlState, $this->transientSqlStates, true)) {
            return true;
        }

        // MySQL deadlock and lock wait timeout (configurable)
        if (in_array($driverCode, $this->transientErrorCodes, true)) {
            return true;
        }

        // If driver code is unavailable, try to infer by looking for known codes inside the message
        if ($driverCode === null && $this->containsAnyErrorCode($msg, $this->transientErrorCodes)) {
            return true;
        }

        // Heuristic by message
        foreach ($this->transientMsgNeedles as $n) {
            if (str_contains($msg, $n)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks whether any of the given numeric error codes appears as a standalone token in the message.
     */
    private function containsAnyErrorCode(string $msg, array $codes): bool
    {
        foreach ($codes as $code) {
            $pattern = '/(?<!\d)'.preg_quote((string)$code, '/').'(?!\d)/';
            if (preg_match($pattern, $msg) === 1) {
                return true;
            }
        }

        return false;
    }
}
