<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL;

use AEATech\TransactionManager\MySQL\Transaction\InsertMode;
use AEATech\TransactionManager\MySQL\Transaction\InsertOnDuplicateKeyUpdateTransactionFactory;
use AEATech\TransactionManager\MySQL\Transaction\InsertTransactionFactory;
use AEATech\TransactionManager\MySQL\Transaction\SqlTransaction;
use AEATech\TransactionManager\TransactionInterface;
use InvalidArgumentException;

/**
 * Convenience facade for creating MySQL-specific TransactionInterface instances.
 *
 * This class hides low-level details (InsertValuesBuilder, InsertMode, etc.)
 * behind a small set of high-level factory methods.
 *
 * Typical usage:
 *
 *     $transactions = new TransactionsFactory(
 *         insertTransactionFactory: $insertFactory,
 *         insertOnDuplicateKeyUpdateTransactionFactory: $upsertFactory,
 *     );
 *
 *     $tx = $transactions->createInsert(
 *         tableName: 'users',
 *         rows: [
 *             ['id' => 1, 'email' => 'foo@example.com'],
 *             ['id' => 2, 'email' => 'bar@example.com'],
 *         ],
 *         columnTypes: ['id' => \PDO::PARAM_INT],
 *         isIdempotent: false,
 *     );
 *
 *     $runResult = $transactionManager->run($tx, $options);
 */
class TransactionsFactory
{
    public function __construct(
        private readonly InsertTransactionFactory $insertTransactionFactory,
        private readonly InsertOnDuplicateKeyUpdateTransactionFactory $insertOnDuplicateKeyUpdateTransactionFactory
    ) {
    }

    /**
     * Creates an INSERT transaction:
     *
     *     INSERT INTO tableName (col1, col2, ...)
     *     VALUES (...), (...), ...
     *
     * @param string $tableName
     *   Logical table name without quoting. The underlying implementation will
     *   quote the identifier as needed. The name should:
     *   - be non-empty;
     *   - correspond to an existing table in the current MySQL schema;
     *   - be provided without backticks or other quoting characters.
     *
     * @param array<array<string, mixed>> $rows
     *   Non-empty list of rows to insert.
     *
     *   Each row is an associative array:
     *
     *       [
     *           'column_name' => value,
     *           'other_column' => value,
     *           // ...
     *       ]
     *
     *   Recommended constraints:
     *   - All rows should use the same set of keys (column names).
     *   - Keys are unquoted column names; quoting is handled internally.
     *   - Values should be scalar, null, or objects supported by your DBAL
     *     type system (e.g. \DateTimeInterface when using appropriate types).
     *
     *   An empty array of rows, or a set of rows with inconsistent column sets,
     *   may result in an InvalidArgumentException from the underlying builder.
     *
     * @param array<string, int|string> $columnTypes
     *   Optional mapping of the column name => parameter type for Doctrine DBAL.
     *
     *   Examples:
     *
     *       [
     *           'id'    => \PDO::PARAM_INT,
     *           'email' => \Doctrine\DBAL\ParameterType::STRING,
     *       ]
     *
     *   Notes:
     *   - Keys must match column names used in $rows.
     *   - Values should be compatible with Doctrine DBAL parameter types
     *     (PDO::PARAM_* or \Doctrine\DBAL\ParameterType::* or type names
     *     understood by your DBAL configuration).
     *   - Types are optional; when omitted, DBAL will infer them.
     *
     * @param bool $isIdempotent
     *   Indicates whether this transaction is safe to retry in case of
     *   transient errors according to your retry policy.
     *
     *   Typical semantics:
     *   - false (default): a re-run may change the number of rows inserted
     *     (e.g. duplicate inserts), so only "pre-commit" retries are allowed.
     *   - true: the transaction is designed to be idempotent (e.g. inserts
     *     into a table with unique keys where duplicates are filtered or
     *     logically harmless), and the TransactionManager is allowed to apply
     *     more aggressive retry strategies (e.g. for "commit unknown" cases).
     *
     *   The actual behavior depends on your TransactionManager implementation.
     *
     * Validation / errors:
     *   - This method itself does NOT validate the shape of $rows or $columnTypes
     *     and does not throw exceptions.
     *   - If the provided data is inconsistent (e.g. empty $rows, different
     *     column sets per row, invalid identifiers), an InvalidArgumentException
     *     (or another domain-specific exception) may be thrown later, when the
     *     transaction is built/executed via TransactionManager::run().
     *
     * @return TransactionInterface
     */
    public function createInsert(
        string $tableName,
        array $rows,
        array $columnTypes = [],
        bool $isIdempotent = false,
    ): TransactionInterface {
        return $this->insertTransactionFactory->factory(
            tableName: $tableName,
            rows: $rows,
            columnTypes: $columnTypes,
            isIdempotent: $isIdempotent
        );
    }

    /**
     * Creates an INSERT IGNORE transaction:
     *
     *     INSERT IGNORE INTO tableName (col1, col2, ...)
     *     VALUES (...), (...), ...
     *
     * This variant ignores constraint violations (e.g. duplicate key errors)
     * and continues inserting other rows when possible.
     *
     * @param string $tableName
     *   Logical table name, same rules as in {@see createInsert()}:
     *   - non-empty;
     *   - unquoted;
     *   - must refer to an existing table in the current schema.
     *
     * @param array<array<string, mixed>> $rows
     *   Non-empty list of rows to insert. Same structure and expectations
     *   as for {@see createInsert()}.
     *
     *   Example:
     *
     *       $rows = [
     *           ['id' => 1, 'sku' => 'A-001'],
     *           ['id' => 2, 'sku' => 'A-002'],
     *       ];
     *
     * @param array<string, int|string> $columnTypes
     *   Optional mapping of column name => Doctrine DBAL parameter type.
     *   Same rules as in {@see createInsert()}.
     *
     * @param bool $isIdempotent
     *   See {@see createInsert()} for general semantics.
     *
     *   For INSERT IGNORE specifically:
     *   - it is usually still considered non-idempotent by default, because
     *     the first run may successfully insert a row, and subsequent runs
     *     may silently skip it, changing the "affected rows" semantics;
     *   - set to true only if your business logic explicitly designs this
     *     statement to be idempotent and your retry policy is aware of that.
     *
     * Validation / errors:
     *   - This method itself does NOT validate the shape of $rows or $columnTypes
     *     and does not throw exceptions.
     *   - If the provided data is inconsistent (e.g. empty $rows, different
     *     column sets per row, invalid identifiers), an InvalidArgumentException
     *     (or another domain-specific exception) may be thrown later, when the
     *     transaction is built/executed via TransactionManager::run().
     *
     * @return TransactionInterface
     */
    public function createInsertIgnore(
        string $tableName,
        array $rows,
        array $columnTypes = [],
        bool $isIdempotent = false,
    ): TransactionInterface {
        return $this->insertTransactionFactory->factory(
            tableName: $tableName,
            rows: $rows,
            columnTypes: $columnTypes,
            insertMode: InsertMode::Ignore,
            isIdempotent: $isIdempotent
        );
    }

    /**
     * Creates an INSERT ... ON DUPLICATE KEY UPDATE transaction:
     *
     *     INSERT INTO tableName (col1, col2, ...)
     *     VALUES (...), (...), ...
     *     ON DUPLICATE KEY UPDATE
     *         colX = VALUES(colX),
     *         colY = VALUES(colY),
     *         ...
     *
     * This is a classic MySQL "upsert" by PRIMARY KEY / UNIQUE constraint.
     *
     * @param string $tableName
     *   Logical table name, same conventions as in {@see createInsert()}:
     *   - non-empty;
     *   - unquoted;
     *   - must refer to an existing table.
     *
     * @param array<array<string, mixed>> $rows
     *   Non-empty list of rows to insert or update.
     *
     *   Requirements / recommendations:
     *   - Each row is an associative array of column => value pairs.
     *   - All rows should contain at least the columns required by:
     *       - the PRIMARY KEY / UNIQUE constraints used to detect duplicates;
     *       - the union of all columns referenced in $updateColumns.
     *   - All rows should use a consistent set of keys; mismatched keys may
     *     result in an InvalidArgumentException.
     *
     *   Example:
     *
     *       $rows = [
     *           ['id' => 1, 'email' => 'foo@example.com', 'name' => 'Foo'],
     *           ['id' => 2, 'email' => 'bar@example.com', 'name' => 'Bar'],
     *       ];
     *
     * @param string[] $updateColumns
     *   List of column names that should be updated when a duplicate key
     *   conflict is detected.
     *
     *   Constraints:
     *   - Array should be non-empty for meaningful upsert behavior.
     *   - Each column name must:
     *       - be present as a key in every row in $rows;
     *       - refer to a valid, updatable column in the target table.
     *   - Column names are passed unquoted; quoting is handled internally.
     *
     *   Example:
     *
     *       $updateColumns = ['email', 'name'];
     *
     *   This will generate:
     *
     *       ON DUPLICATE KEY UPDATE
     *           email = VALUES(email),
     *           name  = VALUES(name)
     *
     * @param array<string, int|string> $columnTypes
     *   Optional mapping of column name => Doctrine DBAL parameter type.
     *   Same semantics as in {@see createInsert()}.
     *
     * @param bool $isIdempotent
     *   Indicates whether the upsert is considered idempotent in the context
     *   of your application/retry policy.
     *
     *   Typical considerations:
     *   - Upserts are often *closer* to idempotent than plain INSERT, since
     *     repeating the same statement with the same data usually converges
     *     to the same final row state.
     *   - However, if your upsert logic depends on triggers, timestamps,
     *     counters, or other side effects, repeat execution may still change
     *     more than you expect.
     *
     *   Set to true only if you have carefully reviewed the semantics for
     *   your table and retry strategy.
     *
     * Validation / errors:
     *  - This method itself does NOT validate the shape of $rows or $columnTypes
     *    and does not throw exceptions.
     *  - If the provided data is inconsistent (e.g. empty $rows, different
     *    column sets per row, invalid identifiers), an InvalidArgumentException
     *    (or another domain-specific exception) may be thrown later, when the
     *    transaction is built/executed via TransactionManager::run().
     *
     * @return TransactionInterface
     *
     * @throws InvalidArgumentException
     *   Thrown if:
     *   - $updateColumns is empty
     */
    public function createInsertOnDuplicateKeyUpdate(
        string $tableName,
        array $rows,
        array $updateColumns,
        array $columnTypes = [],
        bool $isIdempotent = false,
    ): TransactionInterface {
        return $this->insertOnDuplicateKeyUpdateTransactionFactory->factory(
            tableName: $tableName,
            rows: $rows,
            updateColumns: $updateColumns,
            columnTypes: $columnTypes,
            isIdempotent: $isIdempotent
        );
    }

    /**
     * Create a transaction that wraps *raw* SQL provided by the caller.
     *
     * This is an **escape hatch** for advanced use-cases where a strongly typed
     * transaction (e.g. `createInsert*()`) is not available or not practical.
     *
     * **WARNING – use at your own risk:**
     *
     * - The Transaction Manager does **not** validate, normalize, or sanitize the SQL.
     *   Whatever you pass here will be sent directly to the underlying connection.
     * - It is the caller's responsibility to ensure that the SQL:
     *   - matches the current database engine and schema;
     *   - does not break the invariants of your application;
     *   - does not introduce SQL injection or other security issues.
     * - The `TransactionManager` will still wrap this statement in a database
     *   transaction and apply retry logic according to the `isIdempotent` flag,
     *   but it **cannot** know whether repeating this SQL is actually safe.
     *
     * Typical scenarios:
     * - one-off maintenance or migration queries;
     * - ad-hoc bulk updates that are easier to express as raw SQL;
     * - integration cases where a higher-level transaction type does not yet exist.
     *
     * This method itself never throws `InvalidArgumentException`. Any issues with
     * the SQL, parameters, or types will surface later when:
     * - `Transaction::build()` is called by `TransactionManager`, or
     * - the underlying driver/DBAL attempts to execute the query.
     *
     * @param string $sql Arbitrary SQL statement (typically a single DML statement
     *                    such as `INSERT`, `UPDATE` or `DELETE`). Although the
     *                    transaction manager is designed for write operations,
     *                    nothing prevents you from executing `SELECT` here, but
     *                    the library does not optimize for read-only workloads.
     * @param array<int|string, mixed> $params
     *        Positional (`[0 => ...]`) or named (`[':foo' => ...]`) parameters
     *        compatible with the underlying DBAL/driver.
     * @param array<int|string, int|string> $types
     *        Optional parameter types, e.g., Doctrine DBAL type constants, or
     *        `PDO::PARAM_*` values, depending on the adapter.
     * @param bool $isIdempotent
     *        Set this flag to `true` **only if** it is safe to re-execute the
     *        SQL in case of a transient failure (e.g., lock wait timeout or
     *        connection drop), and you are prepared to accept the side effects
     *        of possible retries. Otherwise, keep it `false`.
     *
     * @return TransactionInterface
     */
    public function createSql(
        string $sql,
        array $params = [],
        array $types = [],
        bool $isIdempotent = false,
    ): TransactionInterface {
        return new SqlTransaction($sql, $params, $types, $isIdempotent);
    }
}
