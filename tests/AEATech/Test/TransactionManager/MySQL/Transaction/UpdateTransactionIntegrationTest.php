<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL\Transaction;

use AEATech\Test\TransactionManager\MySQL\IntegrationTestCase;
use AEATech\TransactionManager\MySQL\Internal\InsertValuesBuilder;
use AEATech\TransactionManager\MySQL\Transaction\InsertTransaction;
use AEATech\TransactionManager\MySQL\Transaction\UpdateTransaction;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

#[Group('integration')]
#[CoversClass(UpdateTransaction::class)]
class UpdateTransactionIntegrationTest extends IntegrationTestCase
{
    private const TABLE_NAME = 'tm_update_test';
    private const IDENTIFIER_COLUMN = 'id';
    private const UPDATE_COLUMN_1 = 'column_1';
    private const UPDATE_COLUMN_2 = 'column_2';

    private const INIT_STATE = [
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

    private const COLUMNS_WITH_VALUES_FOR_UPDATE = [
        self::UPDATE_COLUMN_1 => 'updated value',
        self::UPDATE_COLUMN_2 => 200500,
    ];

    private const COLUMN_TYPES = [
        self::UPDATE_COLUMN_1 => ParameterType::STRING,
        self::UPDATE_COLUMN_2 => ParameterType::INTEGER,
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
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function updateSuccessfully(): void
    {
        /**
         * Init state
         */
        $initTransaction = new InsertTransaction(new InsertValuesBuilder(),self::TABLE_NAME, self::INIT_STATE);
        $affectedRows = $this->runTransaction($initTransaction);
        self::assertSame(count(self::INIT_STATE), $affectedRows);

        /**
         * Test update
         */
        $identifiers = [];
        $expected = [];
        foreach (self::INIT_STATE as $index => $item) {
            if ($index % 2 === 0) {
                $identifiers[] = $item[self::IDENTIFIER_COLUMN];
                $item[self::UPDATE_COLUMN_1] = self::COLUMNS_WITH_VALUES_FOR_UPDATE[self::UPDATE_COLUMN_1];
                $item[self::UPDATE_COLUMN_2] = self::COLUMNS_WITH_VALUES_FOR_UPDATE[self::UPDATE_COLUMN_2];
            }

            $expected[] = $item;
        }

        $updateTransaction = new UpdateTransaction(
            self::TABLE_NAME,
            self::IDENTIFIER_COLUMN,
            ParameterType::INTEGER,
            $identifiers,
            self::COLUMNS_WITH_VALUES_FOR_UPDATE,
            self::COLUMN_TYPES,
        );
        $affectedRows = $this->runTransaction($updateTransaction);
        self::assertSame(count($identifiers), $affectedRows);

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
