<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL\Transaction;

use AEATech\Test\TransactionManager\MySQL\IntegrationTestCase;
use AEATech\TransactionManager\MySQL\MySQLIdentifierQuoter;
use AEATech\TransactionManager\Transaction\InsertTransaction;
use AEATech\TransactionManager\Transaction\Internal\InsertValuesBuilder;
use Doctrine\DBAL\ParameterType;
use Throwable;

abstract class AbstractUpdateTransactionIntegrationTestCase extends IntegrationTestCase
{
    protected const TABLE_NAME = 'tm_update_test';
    protected const IDENTIFIER_COLUMN = 'id';
    protected const UPDATE_COLUMN_1 = 'column_1';
    protected const UPDATE_COLUMN_2 = 'column_2';

    protected const COLUMN_TYPES = [
        self::UPDATE_COLUMN_1 => ParameterType::STRING,
        self::UPDATE_COLUMN_2 => ParameterType::INTEGER,
    ];

    protected const INIT_STATE = [
        [
            self::IDENTIFIER_COLUMN => 1,
            self::UPDATE_COLUMN_1 => 'value 1',
            self::UPDATE_COLUMN_2 => 100501,
        ],
        [
            self::IDENTIFIER_COLUMN => 2,
            self::UPDATE_COLUMN_1 => 'value 2',
            self::UPDATE_COLUMN_2 => 100502,
        ],
        [
            self::IDENTIFIER_COLUMN => 3,
            self::UPDATE_COLUMN_1 => 'value 3',
            self::UPDATE_COLUMN_2 => 100503,
        ],
        [
            self::IDENTIFIER_COLUMN => 4,
            self::UPDATE_COLUMN_1 => 'value 4',
            self::UPDATE_COLUMN_2 => 100504,
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        /** @noinspection SqlType */
        self::db()->executeStatement(sprintf(
            'CREATE TABLE %s (%s INT PRIMARY KEY, %s VARCHAR(64) NOT NULL, %s INT NOT NULL) ENGINE=InnoDB',
            self::TABLE_NAME,
            self::IDENTIFIER_COLUMN,
            self::UPDATE_COLUMN_1,
            self::UPDATE_COLUMN_2
        ));

        /**
         * Init state
         */
        $initTransaction = new InsertTransaction(
            new InsertValuesBuilder(),
            new MySQLIdentifierQuoter(),
            self::TABLE_NAME,
            self::INIT_STATE
        );

        $affectedRows = $this->runTransaction($initTransaction);

        self::assertSame(count(self::INIT_STATE), $affectedRows);
    }

    /**
     * @throws Throwable
     */
    protected static function assertState(array $expected): void
    {
        $actual = self::db()
            ->executeQuery(sprintf(
                'SELECT %s, %s, %s FROM %s ORDER BY %s',
                self::IDENTIFIER_COLUMN,
                self::UPDATE_COLUMN_1,
                self::UPDATE_COLUMN_2,
                self::TABLE_NAME,
                self::IDENTIFIER_COLUMN
            ))
            ->fetchAllAssociative();

        self::assertSame($expected, $actual);
    }
}
