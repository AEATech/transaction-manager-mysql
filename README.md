# AEATech Transaction Manager – MySQL

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
use AEATech\TransactionManager\DoctrineAdapter\DbalConnectionAdapter;
use AEATech\TransactionManager\ExecutionPlanBuilder;
use AEATech\TransactionManager\ExponentialBackoff;
use AEATech\TransactionManager\IsolationLevel;
use AEATech\TransactionManager\MySQL\Internal\InsertValuesBuilder;
use AEATech\TransactionManager\MySQL\MySQLErrorClassifier;
use AEATech\TransactionManager\MySQL\Transaction\InsertOnDuplicateKeyUpdateTransactionFactory;
use AEATech\TransactionManager\MySQL\Transaction\InsertTransactionFactory;
use AEATech\TransactionManager\MySQL\TransactionsFactory as MySqlTxFactory;
use AEATech\TransactionManager\RetryPolicy;
use AEATech\TransactionManager\SystemSleeper;
use AEATech\TransactionManager\TransactionManager;
use AEATech\TransactionManager\TxOptions;

// 1) Create a connection adapter (Doctrine DBAL example):
// $dbal = new Doctrine\DBAL\Connection(...);
$conn = new DbalConnectionAdapter($dbal);

// 2) Configure the TransactionManager from the core:
$tm = new TransactionManager(
    executionPlanBuilder: new ExecutionPlanBuilder(),
    connection: $conn,
    errorClassifier: new MySQLErrorClassifier(),
    sleeper: new SystemSleeper(),
);

// 3) Create the MySQL transactions factory
$insertValuesBuilder = new InsertValuesBuilder();

$txFactory = new MySqlTxFactory(
    insertTransactionFactory: new InsertTransactionFactory(
        $insertValuesBuilder
    ),
    insertOnDuplicateKeyUpdateTransactionFactory: new InsertOnDuplicateKeyUpdateTransactionFactory(
        $insertValuesBuilder
    ),
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

## Parameters and types
- rows: array of homogeneous associative arrays like `['column' => value, ...]`. All rows must have the same set of keys (columns).
- columnTypes: `array<string, int|string>` — mapping `column => parameter type` (PDO::PARAM_*, `Doctrine\DBAL\ParameterType::*` or string type names supported by DBAL). Optional — DBAL will try to infer types.
- isIdempotent: a flag for the transaction manager indicating retry safety. Semantics depend on your retry policy:
  - false (default): a re-run may change the outcome (e.g., insert duplicates).
  - true: the statement is designed to be idempotent (e.g., UPSERT by a unique key), allowing the manager to apply more aggressive retries.

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
- Identifier quoting:
  - Pass names without backticks — the library quotes them automatically.
- MySQL 5.7 vs 8.x:
  - The `ON DUPLICATE KEY UPDATE` syntax is the same; ensure compatible types/charsets. The test matrix includes runs against 5.7 and 8.x.

## Running tests

### 1) Via Docker Compose (recommended for reproducibility)

Bring up services for your target PHP/MySQL versions and run PHPUnit inside the PHP CLI containers.

Start services (PHP 8.2/8.3/8.4 with MySQL 8.0; and a dedicated PHP 8.2 image for MySQL 5.7):

```bash
docker-compose -p aeatech-transaction-manager-mysql -f docker/docker-compose.yml up -d
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

To stop and remove containers:

```bash
docker-compose -p aeatech-transaction-manager-mysql -f docker/docker-compose.yml down -v
```

## License

MIT License. See [LICENSE](./LICENSE) for details.
