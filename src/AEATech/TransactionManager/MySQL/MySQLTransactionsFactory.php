<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL;

use AEATech\TransactionManager\MySQL\Transaction\DeleteWithLimitTransactionFactory;
use AEATech\TransactionManager\MySQL\Transaction\InsertIgnoreTransactionFactory;
use AEATech\TransactionManager\MySQL\Transaction\InsertOnDuplicateKeyUpdateTransactionFactory;
use AEATech\TransactionManager\StatementReusePolicy;
use AEATech\TransactionManager\Transaction\DeleteTransactionFactory;
use AEATech\TransactionManager\Transaction\InsertTransactionFactory;
use AEATech\TransactionManager\Transaction\SqlTransaction;
use AEATech\TransactionManager\Transaction\UpdateTransactionFactory;
use AEATech\TransactionManager\Transaction\UpdateWhenThenTransactionFactory;
use AEATech\TransactionManager\TransactionInterface;

/**
 * Convenience facade for creating MySQL-specific TransactionInterface instances.
 *
 * This class hides low-level details (InsertValuesBuilder, InsertMode, etc.)
 * behind a small set of high-level factory methods.
 *
 * Typical usage:
 *
 * $transactions = new MySQLTransactionsFactory(
 *     insertTransactionFactory: $insertTransactionFactory,
 *     insertIgnoreTransactionFactory: $insertIgnoreTransactionFactory,
 *     ...,
 * );
 *
 * $tx = $transactions->createInsert(
 *     tableName: 'users',
 *     rows: [
 *         ['id' => 1, 'email' => 'foo@example.com'],
 *         ['id' => 2, 'email' => 'bar@example.com'],
 *     ],
 *     columnTypes: ['id' => \PDO::PARAM_INT],
 *     isIdempotent: false,
 * );
 *
 * $runResult = $transactionManager->run($tx, $options);
 */
class MySQLTransactionsFactory implements MySQLTransactionsFactoryInterface
{
    public function __construct(
        private readonly InsertTransactionFactory $insertTransactionFactory,
        private readonly InsertIgnoreTransactionFactory $insertIgnoreTransactionFactory,
        private readonly InsertOnDuplicateKeyUpdateTransactionFactory $insertOnDuplicateKeyUpdateTransactionFactory,
        private readonly DeleteTransactionFactory $deleteTransactionFactory,
        private readonly DeleteWithLimitTransactionFactory $deleteWithLimitTransactionFactory,
        private readonly UpdateTransactionFactory $updateTransactionFactory,
        private readonly UpdateWhenThenTransactionFactory $updateWhenThenTransactionFactory,
    ) {
    }

    public function createInsert(
        string $tableName,
        array $rows,
        array $columnTypes = [],
        bool $isIdempotent = false,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): TransactionInterface {
        return $this->insertTransactionFactory->factory(
            tableName: $tableName,
            rows: $rows,
            columnTypes: $columnTypes,
            isIdempotent: $isIdempotent,
            statementReusePolicy: $statementReusePolicy
        );
    }

    public function createInsertIgnore(
        string $tableName,
        array $rows,
        array $columnTypes = [],
        bool $isIdempotent = false,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): TransactionInterface {
        return $this->insertIgnoreTransactionFactory->factory(
            tableName: $tableName,
            rows: $rows,
            columnTypes: $columnTypes,
            isIdempotent: $isIdempotent,
            statementReusePolicy: $statementReusePolicy
        );
    }

    public function createInsertOnDuplicateKeyUpdate(
        string $tableName,
        array $rows,
        array $updateColumns,
        array $columnTypes = [],
        bool $isIdempotent = false,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): TransactionInterface {
        return $this->insertOnDuplicateKeyUpdateTransactionFactory->factory(
            tableName: $tableName,
            rows: $rows,
            updateColumns: $updateColumns,
            columnTypes: $columnTypes,
            isIdempotent: $isIdempotent,
            statementReusePolicy: $statementReusePolicy
        );
    }

    public function createSql(
        string $sql,
        array $params = [],
        array $types = [],
        bool $isIdempotent = false,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): TransactionInterface {
        return new SqlTransaction($sql, $params, $types, $isIdempotent, $statementReusePolicy);
    }

    public function createDelete(
        string $tableName,
        string $identifierColumn,
        mixed $identifierColumnType,
        array $identifiers,
        bool $isIdempotent = true,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): TransactionInterface {
        return $this->deleteTransactionFactory->factory(
            tableName: $tableName,
            identifierColumn: $identifierColumn,
            identifierColumnType: $identifierColumnType,
            identifiers: $identifiers,
            isIdempotent: $isIdempotent,
            statementReusePolicy: $statementReusePolicy
        );
    }

    public function createDeleteWithLimit(
        string $tableName,
        string $identifierColumn,
        mixed $identifierColumnType,
        array $identifiers,
        int $limit,
        bool $isIdempotent = true,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): TransactionInterface {
        return $this->deleteWithLimitTransactionFactory->factory(
            tableName: $tableName,
            identifierColumn: $identifierColumn,
            identifierColumnType: $identifierColumnType,
            identifiers: $identifiers,
            limit: $limit,
            isIdempotent: $isIdempotent,
            statementReusePolicy: $statementReusePolicy
        );
    }

    public function createUpdate(
        string $tableName,
        string $identifierColumn,
        mixed $identifierColumnType,
        array $identifiers,
        array $columnsWithValuesForUpdate,
        array $columnTypes = [],
        bool $isIdempotent = true,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): TransactionInterface {
        return $this->updateTransactionFactory->factory(
            tableName: $tableName,
            identifierColumn: $identifierColumn,
            identifierColumnType: $identifierColumnType,
            identifiers: $identifiers,
            columnsWithValuesForUpdate: $columnsWithValuesForUpdate,
            columnTypes: $columnTypes,
            isIdempotent: $isIdempotent,
            statementReusePolicy: $statementReusePolicy
        );
    }

    public function createUpdateWhenThen(
        string $tableName,
        array $rows,
        string $identifierColumn,
        mixed $identifierColumnType,
        array $updateColumns,
        array $updateColumnTypes = [],
        bool $isIdempotent = true,
        StatementReusePolicy $statementReusePolicy = StatementReusePolicy::None
    ): TransactionInterface {
        return $this->updateWhenThenTransactionFactory->factory(
            tableName: $tableName,
            rows: $rows,
            identifierColumn: $identifierColumn,
            identifierColumnType: $identifierColumnType,
            updateColumns: $updateColumns,
            updateColumnTypes: $updateColumnTypes,
            isIdempotent: $isIdempotent,
            statementReusePolicy: $statementReusePolicy
        );
    }
}
