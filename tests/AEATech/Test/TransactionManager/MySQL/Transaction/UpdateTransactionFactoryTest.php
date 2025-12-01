<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\Transaction\UpdateTransaction;
use AEATech\TransactionManager\MySQL\Transaction\UpdateTransactionFactory;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateTransactionFactory::class)]
class UpdateTransactionFactoryTest extends TestCase
{
    private const COLUMN_1 = 'column_1';
    private const COLUMN_2 = 'column_2';

    private const TABLE_NAME = 'tm_update_test';
    private const IDENTIFIER_COLUMN = 'identifier_column';
    private const IDENTIFIER_COLUMN_TYPE = ParameterType::INTEGER;
    private const IDENTIFIERS = [1, 2, 3];
    private const COLUMNS_WITH_VALUES_FOR_UPDATE = [
        self::COLUMN_1 => 'value for update',
        self::COLUMN_2 => 100500,
    ];
    private const COLUMN_TYPES = [
        self::COLUMN_1 => ParameterType::STRING,
        self::COLUMN_2 => ParameterType::INTEGER,
    ];
    private const IS_IDEMPOTENT = false;

    #[Test]
    public function factory(): void
    {
        $expected = new UpdateTransaction(
            self::TABLE_NAME,
            self::IDENTIFIER_COLUMN,
            self::IDENTIFIER_COLUMN_TYPE,
            self::IDENTIFIERS,
            self::COLUMNS_WITH_VALUES_FOR_UPDATE,
            self::COLUMN_TYPES,
            self::IS_IDEMPOTENT,
        );

        $actual = (new UpdateTransactionFactory())->factory(
            self::TABLE_NAME,
            self::IDENTIFIER_COLUMN,
            self::IDENTIFIER_COLUMN_TYPE,
            self::IDENTIFIERS,
            self::COLUMNS_WITH_VALUES_FOR_UPDATE,
            self::COLUMN_TYPES,
            self::IS_IDEMPOTENT,
        );

        self::assertEquals($expected, $actual);
    }
}
