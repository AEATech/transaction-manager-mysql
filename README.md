# AEATech Transaction Manager – MySQL

![Code Coverage](.build/coverage_badge.svg)

Lightweight module for generating safe and efficient MySQL statements:
- INSERT
- INSERT IGNORE
- INSERT ... ON DUPLICATE KEY UPDATE (UPSERT)

This package is an extension of `aeatech/transaction-manager-core`.
It only builds SQL and parameters; the core package handles execution, retries, and transaction boundaries.
For Doctrine DBAL users, there is an adapter package: `aeatech/transaction-manager-doctrine-adapter`.

System requirements:
- PHP >= 8.2
- ext-pdo
- MySQL 5.7+ or 8.x

Installation (Composer):
```bash
composer require aeatech/transaction-manager-mysql
```

## Quick start

```php
<?php
use AEATech\TransactionManager\DoctrineAdapter\DbalMysqlConnectionAdapter;
use AEATech\TransactionManager\ExecutionPlanBuilder;
use AEATech\TransactionManager\ExponentialBackoff;
use AEATech\TransactionManager\IsolationLevel;
use AEATech\TransactionManager\MySQL\MySQLIdentifierQuoter;
use AEATech\TransactionManager\Transaction\Internal\InsertValuesBuilder;
use AEATech\TransactionManager\Transaction\Internal\UpdateWhenThenDefinitionsBuilder;
use AEATech\TransactionManager\MySQL\Transaction\InsertIgnoreTransactionFactory;
use AEATech\TransactionManager\MySQL\MySQLErrorClassifier;
use AEATech\TransactionManager\MySQL\Transaction\InsertOnDuplicateKeyUpdateTransactionFactory;
use AEATech\TransactionManager\MySQL\Transaction\DeleteWithLimitTransactionFactory;
use AEATech\TransactionManager\MySQL\Transaction\InsertTransactionFactory;
use AEATech\TransactionManager\MySQL\TransactionsFactory as MySqlTxFactory;
use AEATech\TransactionManager\Transaction\DeleteTransactionFactory;
use AEATech\TransactionManager\Transaction\UpdateTransactionFactory;
use AEATech\TransactionManager\Transaction\UpdateWhenThenTransactionFactory;
use AEATech\TransactionManager\RetryPolicy;
use AEATech\TransactionManager\SystemSleeper;
use AEATech\TransactionManager\TransactionManager;
use AEATech\TransactionManager\TxOptions;

// 1) Create a connection adapter (Doctrine DBAL example):
// $dbal = new Doctrine\DBAL\Connection(...);
$conn = new DbalMysqlConnectionAdapter($dbal);

// 2) Configure the TransactionManager from the core:
$tm = new TransactionManager(
    executionPlanBuilder: new ExecutionPlanBuilder(),
    connection: $conn,
    errorClassifier: new MySQLErrorClassifier(),
    defaultRetryPolicy: new RetryPolicy(3, new ExponentialBackoff()),
    sleeper: new SystemSleeper(),
);

// 3) Create the MySQL transactions factory
$quoter = new MySQLIdentifierQuoter();
$insertValuesBuilder = new InsertValuesBuilder();
$updateWhenThenDefs = new UpdateWhenThenDefinitionsBuilder();

$txFactory = new MySqlTxFactory(
    insertTransactionFactory: new InsertTransactionFactory(
        $insertValuesBuilder,
        $quoter,
    ),
    insertIgnoreTransactionFactory: new InsertIgnoreTransactionFactory(
        $insertValuesBuilder,
        $quoter,
    ),
    insertOnDuplicateKeyUpdateTransactionFactory: new InsertOnDuplicateKeyUpdateTransactionFactory(
        $insertValuesBuilder,
        $quoter,
    ),
    deleteTransactionFactory: new DeleteTransactionFactory($quoter),
    deleteWithLimitTransactionFactory: new DeleteWithLimitTransactionFactory($quoter),
    updateTransactionFactory: new UpdateTransactionFactory($quoter),
    updateWhenThenTransactionFactory: new UpdateWhenThenTransactionFactory($updateWhenThenDefs, $quoter),
);

// 4) Example: regular INSERT
$tx = $txFactory->createInsert(
    tableName: 'users',
    rows: [
        ['id' => 1, 'email' => 'foo@example.com', 'name' => 'Foo'],
        ['id' => 2, 'email' => 'bar@example.com', 'name' => 'Bar'],
    ],
    columnTypes: [
        'id' => \PDO::PARAM_INT,
        // other types can be omitted — DBAL will infer them
    ],
    isIdempotent: false,
);

$options = new TxOptions(
    isolationLevel: IsolationLevel::ReadCommitted,
    retryPolicy: new RetryPolicy(3, new ExponentialBackoff())
);

$runResult = $tm->run($tx, $options);
```

## Usage examples

### 1) INSERT
```php
$tx = $txFactory->createInsert(
    tableName: 'audit_log',
    rows: [
        ['event' => 'login', 'user_id' => 10, 'created_at' => new \DateTimeImmutable()],
        ['event' => 'logout', 'user_id' => 10, 'created_at' => new \DateTimeImmutable()],
    ],
    columnTypes: [
        'user_id' => \PDO::PARAM_INT,
        // for dates, use types configured in your DBAL (if needed)
    ],
    isIdempotent: false,
);
$tm->run($tx, $options);
```

### 2) INSERT IGNORE
Ignores constraint violations (e.g., duplicate key errors) and inserts the remaining rows where possible.
```php
$tx = $txFactory->createInsertIgnore(
    tableName: 'products',
    rows: [
        ['id' => 1, 'sku' => 'A-001'],
        ['id' => 2, 'sku' => 'A-002'],
    ],
    columnTypes: ['id' => \PDO::PARAM_INT],
    isIdempotent: false, // usually NOT idempotent in business terms
);
$tm->run($tx, $options);
```

### 3) UPSERT: INSERT ... ON DUPLICATE KEY UPDATE
Updates specified columns when a PRIMARY KEY/UNIQUE conflict is detected.
```php
$tx = $txFactory->createInsertOnDuplicateKeyUpdate(
    tableName: 'users',
    rows: [
        ['id' => 1, 'email' => 'foo@example.com', 'name' => 'Foo'],
        ['id' => 2, 'email' => 'bar@example.com', 'name' => 'Bar'],
    ],
    updateColumns: ['email', 'name'], // which columns to update on duplicates
    columnTypes: ['id' => \PDO::PARAM_INT],
    isIdempotent: true, // in most cases UPSERT is logically idempotent
);
$tm->run($tx, $options);
```

### 4) Raw SQL transaction (`SqlTransaction`)

In advanced scenarios you may want to execute a **custom SQL statement** that is not covered by the built-in MySQL transaction types.  
For this purpose the library provides a low-level escape hatch:

```php
$tx = $txFactory->createSql(
    sql: 'UPDATE users SET last_seen = NOW() WHERE id = :id',
    params: [':id' => $userId],
    types: ['id' => \PDO::PARAM_INT],
    isIdempotent: true // set to true ONLY if retrying this SQL is 100% safe
);
$tm->run($tx, $options);
```

### 5) DELETE by identifiers
Remove a set of rows by primary key (or another unique column).
```php
$tx = $txFactory->createDelete(
    tableName: 'products',
    identifierColumn: 'id',
    identifierColumnType: \PDO::PARAM_INT,
    identifiers: [101, 102, 103],
    isIdempotent: true,
);
$tm->run($tx, $options);
```

### 6) DELETE with LIMIT (MySQL-specific)
Cap the maximum number of deleted rows per statement.
```php
$tx = $txFactory->createDeleteWithLimit(
    tableName: 'logs',
    identifierColumn: 'id',
    identifierColumnType: \PDO::PARAM_INT,
    identifiers: range(1, 1000),
    limit: 100, // delete up to 100 rows in one go
    isIdempotent: true,
);
$tm->run($tx, $options);
```

### 7) UPDATE by identifiers
Set the same values for all targeted rows by identifier list.
```php
$tx = $txFactory->createUpdate(
    tableName: 'orders',
    identifierColumn: 'id',
    identifierColumnType: \PDO::PARAM_INT,
    identifiers: [10, 11, 12],
    columnsWithValuesForUpdate: [
        'status' => 'archived',
        'updated_at' => new \DateTimeImmutable(),
    ],
    columnTypes: [
        'status' => \PDO::PARAM_STR,
        // date/time types follow your DBAL config
    ],
    isIdempotent: true,
);
$tm->run($tx, $options);
```

### 8) UPDATE WHEN ... THEN (per-row values)
Update multiple rows with different values per row using a single statement built with `CASE WHEN`.
```php
$tx = $txFactory->createUpdateWhenThen(
    tableName: 'users',
    rows: [
        ['id' => 1, 'quota' => 100, 'plan' => 'basic'],
        ['id' => 2, 'quota' => 250, 'plan' => 'pro'],
    ],
    identifierColumn: 'id',
    identifierColumnType: \PDO::PARAM_INT,
    updateColumns: ['quota', 'plan'],
    updateColumnTypes: [
        'quota' => \PDO::PARAM_INT,
        'plan'  => \PDO::PARAM_STR,
    ],
    isIdempotent: true,
);
$tm->run($tx, $options);
```

### Prepared Statement Reuse Hint

This package supports the optional prepared statement reuse hint via `StatementReusePolicy` from the Core package. It is a best‑effort performance hint that may be ignored by connection implementations.

Options:

- `StatementReusePolicy::None` — no reuse (default)
- `StatementReusePolicy::PerTransaction` — attempt to reuse within a single DB transaction
- `StatementReusePolicy::PerConnection` — attempt to reuse across transactions while the physical connection remains open

Example with a MySQL transaction factory:

```php
use AEATech\TransactionManager\StatementReusePolicy;

$tx = $txFactory->createInsert(
    tableName: 'users',
    rows: [
        ['id' => 1, 'email' => 'foo@example.com', 'name' => 'Foo'],
    ],
    columnTypes: ['id' => \PDO::PARAM_INT],
    isIdempotent: false,
    statementReusePolicy: StatementReusePolicy::PerTransaction,
);

$tm->run($tx, $options);
```

Notes:

- This is a performance hint only; do not depend on it for correctness or idempotency.
- A connection adapter may ignore the hint due to driver limitations, reconnections, or internal safety choices.

## Parameters and types
- rows: array of homogeneous associative arrays like `['column' => value, ...]`. All rows must have the same set of keys (columns).
- columnTypes: `array<string, int|string>` — mapping `column => parameter type` (PDO::PARAM_*, `Doctrine\DBAL\ParameterType::*` or string type names supported by DBAL). Optional — DBAL will try to infer types.
- isIdempotent: a flag for the transaction manager indicating retry safety. Semantics depend on your retry policy:
  - false (default): a re-run may change the outcome (e.g., insert duplicates).
  - true: the statement is designed to be idempotent (e.g., UPSERT by a unique key), allowing the manager to apply more aggressive retries.

Additional notes for delete/update:
- identifierColumn / identifiers:
  - Use a primary key or another unique column to avoid unintended data changes.
  - Provide a non-empty array of scalar identifiers.
- DELETE with LIMIT:
  - `limit` must be a positive integer; not all provided identifiers may be deleted in one run.
- UPDATE by identifiers:
  - `columnTypes` apply to the columns in `SET` clause; the identifier type is provided separately via `identifierColumnType`.
- UPDATE WHEN ... THEN:
  - `rows` must include the identifier column and all columns listed in `updateColumns`.

## How it works
- SQL and parameters are built inside transactions (`InsertTransaction`, `InsertOnDuplicateKeyUpdateTransaction`).
- Identifiers (table and column names) are quoted safely for MySQL.
- The result is an `AEATech\TransactionManager\Query` (from the core), which is executed via the provided connection adapter.

## Edge cases and recommendations
- Empty `rows` array:
  - Not supported. Provide at least one row; otherwise an exception will be thrown during build/execute.
- Inconsistent columns across rows:
  - All rows must use the same set of keys. Differences will lead to an exception.
- Types and NULL:
  - If you use `columnTypes`, account for `NULL` and dates/JSON. For complex types follow your Doctrine DBAL settings.
- Large batches:
  - Insert in batches (e.g., 100–1000 rows) to avoid driver/package limits and overly large statements.
- UPSERT and unique keys:
  - For `createInsertOnDuplicateKeyUpdate`, a PRIMARY KEY or `UNIQUE` index must exist to detect conflicts. Columns from `updateColumns` must be present in each row.
- INSERT IGNORE semantics:
  - MySQL may silently skip rows that violate constraints. This is often non-idempotent from a business perspective.
- DELETE semantics:
  - Deleting the same identifiers twice is typically idempotent (2nd run affects 0 rows), but consider triggers/cascades/side effects.
- DELETE with LIMIT:
  - Use for chunking large deletions; combine with application-side iteration for full cleanup.
- UPDATE semantics:
  - Ensure your `WHERE` criteria (identifiers) uniquely target intended rows. Consider idempotency of repeated updates.
- Identifier quoting:
  - Pass names without backticks — the library quotes them automatically.
- MySQL 5.7 vs 8.x:
  - The `ON DUPLICATE KEY UPDATE` syntax is the same; ensure compatible types/charsets. The test matrix includes runs against 5.7 and 8.x.

## Running tests

### 1) Via Docker Compose (recommended for reproducibility)

Bring up services for your target PHP/MySQL versions and run PHPUnit inside the PHP CLI containers.

Start services (PHP 8.2/8.3/8.4 with MySQL 8.0; and a dedicated PHP 8.2 image for MySQL 5.7):

```bash
docker-compose -p aeatech-transaction-manager-mysql -f docker/docker-compose.yml up -d --build
```

Install dependencies inside the PHP container (example for PHP 8.3):

```bash
docker-compose -p aeatech-transaction-manager-mysql -f docker/docker-compose.yml exec -T php-cli-8.3 composer install
```

Run tests for PHP 8.2:
```bash
docker-compose -p aeatech-transaction-manager-mysql -f docker/docker-compose.yml exec -T php-cli-8.2 vendor/bin/phpunit
```

For PHP 8.3:
```bash
docker-compose -p aeatech-transaction-manager-mysql -f docker/docker-compose.yml exec -T php-cli-8.3 vendor/bin/phpunit
```

For PHP 8.4:
```bash
docker-compose -p aeatech-transaction-manager-mysql -f docker/docker-compose.yml exec -T php-cli-8.4 vendor/bin/phpunit
```

For PHP 8.2 with MySQL 5.7:
```bash
docker-compose -p aeatech-transaction-manager-mysql -f docker/docker-compose.yml exec -T php-cli-8.2-mysql-5.7 vendor/bin/phpunit
```

Run all configured variants:

```bash
for v in 8.2 8.3 8.4 8.2-mysql-5.7 ; do \
  echo "Testing PHP $v..."; \
  docker-compose -p aeatech-transaction-manager-mysql -f docker/docker-compose.yml exec -T php-cli-$v vendor/bin/phpunit || break; \
done
```

## 4. Run phpstan
```bash
docker-compose -p aeatech-transaction-manager-mysql -f docker/docker-compose.yml exec php-cli-8.4 vendor/bin/phpstan analyse -c phpstan.neon
```

## Stopping the Environment
```bash
docker-compose -p aeatech-transaction-manager-mysql -f docker/docker-compose.yml down -v
```

## License

This project is licensed under the MIT License. See the [LICENSE](./LICENSE) file for details.