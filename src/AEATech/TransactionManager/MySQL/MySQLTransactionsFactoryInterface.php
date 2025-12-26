<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL;

use AEATech\TransactionManager\StatementReusePolicy;
use AEATech\TransactionManager\TransactionInterface;
use InvalidArgumentException;

/**
 * Convenience facade for creating MySQL-specific TransactionInterface instances.
 */
interface MySQLTransactionsFactoryInterface
{
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
     * @param StatementReusePolicy $statementReusePolicy
     *   Controls whether the underlying query builder should reuse the same
     *   statement object for multiple executions.
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
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): TransactionInterface;

    /**
     * Creates an INSERT IGNORE transaction:
     *
     *     INSERT IGNORE INTO tableName (col1, col2, ...)
     *     VALUES (...), (...), ...
     *
     * This variant ignores constraint violations (e.g., duplicate key errors)
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
     *     the first run may successfully insert a row, and later runs
     *     may silently skip it, changing the "affected rows" semantics;
     *   - set to true only if your business logic explicitly designs this
     *     statement to be idempotent and your retry policy is aware of that.
     *
     * @param StatementReusePolicy $statementReusePolicy
     *   Controls whether the underlying query builder should reuse the same
     *   statement object for multiple executions.
     *
     * Validation / errors:
     *   - This method itself does NOT validate the shape of $rows or $columnTypes
     *     and does not throw exceptions.
     *   - If the provided data is inconsistent (e.g., empty $rows, different
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
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): TransactionInterface;

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
     * @param array<string, mixed> $columnTypes
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
     * @param StatementReusePolicy $statementReusePolicy
     *   Controls whether the underlying query builder should reuse the same
     *   statement object for multiple executions.
     *
     * Validation / errors:
     *  - This method itself does NOT validate the shape of $rows or $columnTypes
     *    and does not throw exceptions.
     *  - If the provided data is inconsistent (e.g., empty $rows, different
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
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): TransactionInterface;

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
     * @param array<int|string, mixed> $types
     *        Optional parameter types, e.g., Doctrine DBAL type constants, or
     *        `PDO::PARAM_*` values, depending on the adapter.
     * @param bool $isIdempotent
     *        Set this flag to `true` **only if** it is safe to re-execute the
     *        SQL in case of a transient failure (e.g., lock wait timeout or
     *        connection drop), and you are prepared to accept the side effects
     *        of possible retries. Otherwise, keep it `false`.
     *
     * @param StatementReusePolicy $statementReusePolicy
     *        Controls whether the underlying query builder should reuse the same
     *        statement object for multiple executions.
     *
     * @return TransactionInterface
     */
    public function createSql(
        string $sql,
        array $params = [],
        array $types = [],
        bool $isIdempotent = false,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): TransactionInterface;

    /**
     * Creates a DELETE transaction by identifier column:
     *
     *     DELETE FROM tableName
     *     WHERE identifierColumn IN (?, ?, ...)
     *
     * This is a convenience wrapper around {@see DeleteTransactionFactory},
     * suitable for removing a known, finite set of rows by their identifiers
     * (typically a primary key or other unique column).
     *
     * @param string $tableName
     *   Logical table name without quoting. The underlying implementation will:
     *   - quote the identifier as needed for MySQL;
     *   - expect a non-empty, existing table name in the current schema;
     *   - reject names that already contain backticks or other quoting.
     *
     * @param string $identifierColumn
     *   Name of the column used in the `WHERE ... IN (...)` predicate. In most
     *   cases this should be a primary key or unique key to avoid accidental
     *   removal of unintended rows.
     *
     * @param mixed $identifierColumnType
     *   Type for all identifier values, e.g., a Doctrine DBAL type constant
     *   or `PDO::PARAM_*` value, depending on the adapter.
     *
     * @param array<int, scalar> $identifiers
     *   Non-empty list of identifier values. The values are passed as-is as
     *   positional parameters to the underlying query:
     *
     *       DELETE FROM tableName
     *       WHERE identifierColumn IN (?, ?, ...);
     *
     *   Duplicates are not filtered and will result in repeated values in
     *   the parameter list (which is harmless for the IN predicate).
     *
     * @param bool $isIdempotent
     *   Set to `true` if it is safe to re-execute the delete statement in case
     *   of transient failures (e.g., lock wait timeout, connection drop):
     *
     *   - Deleting the same set of identifiers twice is usually idempotent
     *     at the database level (the second run will affect 0 rows).
     *   - However, if your application expects a very specific "affected rows"
     *     count, or if deletions trigger side effects (cascades, triggers,
     *     denormalized counters, etc.), you MUST review idempotency carefully.
     *
     * @param StatementReusePolicy $statementReusePolicy
     *   Controls whether the underlying query builder should reuse the same
     *   statement object for multiple executions.
     *
     * Validation / errors:
     *   - This method does not validate $identifiers and does not throw on its own.
     *   - If $identifiers are empty or invalid, the underlying transaction
     *     implementation may throw an InvalidArgumentException when the query
     *     is built or executed via TransactionManager::run().
     *
     * @return TransactionInterface
     */
    public function createDelete(
        string $tableName,
        string $identifierColumn,
        mixed $identifierColumnType,
        array $identifiers,
        bool $isIdempotent = true,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): TransactionInterface;

    /**
     * Creates a MySQL-specific DELETE transaction with LIMIT support:
     *
     *     DELETE FROM tableName
     *     WHERE identifierColumn IN (?, ?, ...)
     *     LIMIT N
     *
     * This variant is intended for *defensive* or *chunked* deletions where you
     * want to cap the maximum number of rows deleted in a single statement.
     *
     * **MySQL-specific:**
     *   - `DELETE ... LIMIT` is not portable across all SQL dialects.
     *   - This method should be used only with a MySQL adapter.
     *
     * @param string $tableName
     *   Logical table name without quotes. Same rules as for {@see createDelete()}:
     *   - non-empty;
     *   - unquoted;
     *   - must refer to an existing table in the current schema.
     *
     * @param string $identifierColumn
     *   Name of the column used in the `WHERE ... IN (...)` filter.
     *
     * @param mixed $identifierColumnType
     *   Type for identifier values (Doctrine DBAL type or `PDO::PARAM_*`).
     *
     * @param array<int, scalar> $identifiers
     *   Non-empty list of identifier values. All values are used in the
     *   `IN (...)` predicate, but the LIMIT may prevent all of them from being
     *   deleted in a single execution.
     *
     * @param int $limit
     *   Maximum number of rows to delete in a single statement. Must be a
     *   positive integer. The LIMIT is injected as a literal numeric value
     *   into the generated SQL.
     *
     * @param bool $isIdempotent
     *   This flag controls how the Transaction Manager may apply retries:
     *
     *   - If `$limit` is greater than or equal to `count($identifiers)`, the
     *     behavior is similar to {@see createDelete()} and is typically
     *     idempotent at the database level.
     *   - If `$limit` is *smaller* than `count($identifiers)`, retries may
     *     delete additional rows not deleted in the previous attempt.
     *     In such cases it is usually safer to treat the transaction as
     *     **non-idempotent** and leave `$isIdempotent` set to `false`.
     *
     * @param StatementReusePolicy $statementReusePolicy
     *   Controls whether the underlying query builder should reuse the same
     *   statement object for multiple executions.
     *
     * @return TransactionInterface
     *
     * @throws InvalidArgumentException
     *   Thrown if:
     *   - $identifiers are empty;
     *   - $limit is not a positive integer;
     */
    public function createDeleteWithLimit(
        string $tableName,
        string $identifierColumn,
        mixed $identifierColumnType,
        array $identifiers,
        int $limit,
        bool $isIdempotent = true,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): TransactionInterface;

    /**
     * Creates a bulk UPDATE transaction that applies the same column values to
     * all rows selected by a list of identifiers:
     *
     *     UPDATE tableName
     *     SET col1 = ?, col2 = ?, ...
     *     WHERE identifierColumn IN (?, ?, ...)
     *
     * This is suitable for cases where *all* targeted rows should receive the
     * same updates (e.g., mass status change).
     *
     * @param string $tableName
     *   Logical table name without quoting. Must be:
     *   - non-empty;
     *   - unquoted;
     *   - a valid table in the current MySQL schema.
     *
     * @param string $identifierColumn
     *   Name of the column used in the `WHERE ... IN (...)` clause. Typically,
     *   a primary key or unique key, but the factory does not enforce this.
     *
     * @param mixed $identifierColumnType
     *   Column type for all identifier values (Doctrine DBAL type or
     *   `PDO::PARAM_*`, depending on the adapter).
     *
     * @param array<int, scalar> $identifiers
     *   Non-empty list of identifier values to be updated.
     *
     * @param array<string, mixed> $columnsWithValuesForUpdate
     *   Associative array of columns and the *new* values to apply:
     *
     *       [
     *           'status'     => 'archived',
     *           'updated_at' => new \DateTimeImmutable(...),
     *       ]
     *
     *   The implementation will generate a `SET` clause that assigns these
     *   values to all rows matched by the identifier list.
     *
     * @param array<string, int|string> $columnTypes
     *   Optional per-column type map, e.g., Doctrine DBAL type constants or
     *   `PDO::PARAM_*` values. If a column is not present in this array, a
     *   default type (chosen by the underlying implementation) will be used.
     *
     * @param bool $isIdempotent
     *   Set to `true` if re-executing the UPDATE with the same identifiers and
     *   values is safe for your application logic. Typical scenarios:
     *
     *   - Idempotent: setting deterministic fields (e.g., status flags) to a
     *     fixed value where running the same statement twice has no additional
     *     side effects.
     *   - Non-idempotent: updates that depend on the current value, include
     *     counters, or rely on "rows affected" semantics in business logic.
     *
     * @param StatementReusePolicy $statementReusePolicy
     *   Controls whether the underlying query builder should reuse the same
     *   statement object for multiple executions.
     *
     * Validation / errors:
     *   - This method does not validate the array shapes or detect empty sets.
     *   - If `$identifiers` is empty, or `$columnsWithValuesForUpdate` is
     *     empty, or if the column names are invalid, the underlying transaction
     *     factory may throw InvalidArgumentException when the transaction is
     *     built or executed.
     *
     * @return TransactionInterface
     */
    public function createUpdate(
        string $tableName,
        string $identifierColumn,
        mixed $identifierColumnType,
        array $identifiers,
        array $columnsWithValuesForUpdate,
        array $columnTypes = [],
        bool $isIdempotent = true,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): TransactionInterface;

    /**
     * Creates a CASE-based bulk UPDATE transaction that assigns different
     * values to different rows within a single SQL statement.
     *
     * Generates SQL of the form:
     *
     *     UPDATE tableName
     *     SET
     *       col1 = CASE
     *         WHEN identifierColumn = ? THEN ?
     *         WHEN identifierColumn = ? THEN ?
     *         ...
     *         ELSE col1
     *       END,
     *       col2 = CASE
     *         WHEN identifierColumn = ? THEN ?
     *         ...
     *         ELSE col2
     *       END
     *     WHERE identifierColumn IN (?, ?, ...);
     *
     * This pattern is useful for high-throughput fan-out updates where many
     * rows must be updated with **different** values, but the application
     * performs the update in **one round-trip** instead of N individual UPDATE.
     *
     * @param string $tableName
     *     Logical table name (unquoted). Must refer to an existing table in the
     *     current schema. Actual quoting is handled by the underlying transaction.
     *
     * @param array<int, array<string, mixed>> $rows
     *     List of row descriptors. Each element must contain:
     *
     *         [
     *             $identifierColumn => <scalar identifier>,
     *             <updateColumn1>    => <new value>,
     *             <updateColumn2>    => <new value>,
     *             ...
     *         ]
     *
     *     Example:
     *
     *         [
     *             ['id' => 10, 'status' => 'active',  'score' => 100],
     *             ['id' => 11, 'status' => 'blocked', 'score' => 200],
     *         ]
     *
     *     It is the responsibility of the caller to ensure that:
     *       - every row contains the `$identifierColumn`;
     *       - every row contains values for all `$updateColumns`;
     *       - types of values match the declared `$updateColumnTypes`.
     *
     * @param string $identifierColumn
     *     Name of the discriminator column used both in:
     *       - all CASE WHEN expressions,
     *       - the final WHERE ... IN (...) predicate.
     *
     * @param mixed $identifierColumnType
     *     Doctrine DBAL type or PDO::PARAM_* value for identifier parameters.
     *
     * @param string[] $updateColumns
     *     List of column names that should be updated using CASE WHEN.
     *
     *     For each column in this list the generated SQL will contain:
     *
     *         <column> = CASE
     *             WHEN identifierColumn = ? THEN ?
     *             ...
     *             ELSE <column>
     *         END
     *
     * @param array<string, int|string|null> $updateColumnTypes
     *     Optional per-column type map:
     *
     *         [
     *             'status' => PDO::PARAM_STR,
     *             'score'  => PDO::PARAM_INT,
     *         ]
     *
     *     Any column missing in this array will use a default type (determined by
     *     the transaction implementation).
     *
     * @param bool $isIdempotent
     *     Whether repeating the same update is safe for your business logic.
     *
     *     *Database-level idempotency:*
     *     Running the same CASE-based UPDATE twice normally overwrites fields
     *     with the same values and thus is **idempotent**.
     *
     *     *Application-level non-idempotency:*
     *     If your system triggers domain events, cascades, counters, or other
     *     side effects upon updates, you MUST evaluate idempotency manually.
     *
     * @param StatementReusePolicy $statementReusePolicy
     *     Controls whether the underlying query builder should reuse the same
     *     statement object for multiple executions.
     *
     * @return TransactionInterface
     *     A ready-to-execute transaction that can be passed to
     *     TransactionManager::run().
     */
    public function createUpdateWhenThen(
        string $tableName,
        array $rows,
        string $identifierColumn,
        mixed $identifierColumnType,
        array $updateColumns,
        array $updateColumnTypes = [],
        bool $isIdempotent = true,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): TransactionInterface;
}
