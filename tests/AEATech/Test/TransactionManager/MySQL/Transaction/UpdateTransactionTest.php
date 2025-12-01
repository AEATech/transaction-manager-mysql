<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\Transaction\UpdateTransaction;
use AEATech\TransactionManager\Query;
use Doctrine\DBAL\ParameterType;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateTransaction::class)]
class UpdateTransactionTest extends TestCase
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

    private const EXPECTED_SQL
        = 'UPDATE `' . self::TABLE_NAME . '` ' .
          'SET `' . self::COLUMN_1 . '` = ?, `' . self::COLUMN_2 . '` = ? ' .
          'WHERE `' . self::IDENTIFIER_COLUMN . '` IN (?, ?, ?)';

    #[Test]
    #[DataProvider('buildDataProvider')]
    public function build(array $columnTypes): void
    {
        $transaction = self::createTransaction(columnTypes: $columnTypes);

        $expectedParams = [];
        $expectedTypes = [];

        foreach (self::COLUMNS_WITH_VALUES_FOR_UPDATE as $column => $value) {
            $expectedParams[] = $value;
            $expectedTypes[] = $columnTypes[$column] ?? null;
        }

        foreach (self::IDENTIFIERS as $identifier) {
            $expectedParams[] = $identifier;
            $expectedTypes[] = self::IDENTIFIER_COLUMN_TYPE;
        }

        $expectedTypes = array_filter($expectedTypes);

        $expectedQuery = new Query(self::EXPECTED_SQL, $expectedParams, $expectedTypes);

        /** @noinspection PhpUnhandledExceptionInspection */
        $actualQuery = $transaction->build();

        self::assertEquals($expectedQuery, $actualQuery);
    }

    public static function buildDataProvider(): array
    {
        return [
            [
                'columnTypes' => self::COLUMN_TYPES,
            ],
            [
                'columnTypes' => [
                    self::COLUMN_1 => ParameterType::STRING,
                ],
            ],
            [
                'columnTypes' => [
                    self::COLUMN_2 => ParameterType::INTEGER,
                ],
            ],
        ];
    }

    #[Test]
    #[DataProvider('isIdempotentDataProvider')]
    public function isIdempotent(bool $isIdempotent): void
    {
        $transaction = self::createTransaction(isIdempotent: $isIdempotent);

        self::assertSame($isIdempotent, $transaction->isIdempotent());
    }

    public static function isIdempotentDataProvider(): array
    {
        return [
            [
                'isIdempotent' => true,
            ],
            [
                'isIdempotent' => false,
            ],
        ];
    }

    #[Test]
    public function buildFailedWithEmptyIdentifiers(): void
    {
        $transaction = self::createTransaction(identifiers: []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(UpdateTransaction::MESSAGE_IDENTIFIERS_MUST_NOT_BE_EMPTY);

        /** @noinspection PhpUnhandledExceptionInspection */
        $transaction->build();
    }

    #[Test]
    public function buildFailedWithEmptyColumnsWithValuesForUpdate(): void
    {
        $transaction = self::createTransaction(columnsWithValuesForUpdate: []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(UpdateTransaction::MESSAGE_COLUMNS_WITH_VALUES_FOR_UPDATE_MUST_NOT_BE_EMPTY);

        /** @noinspection PhpUnhandledExceptionInspection */
        $transaction->build();
    }

    private static function createTransaction(
        array $identifiers = self::IDENTIFIERS,
        array $columnsWithValuesForUpdate = self::COLUMNS_WITH_VALUES_FOR_UPDATE,
        array $columnTypes = self::COLUMN_TYPES,
        bool $isIdempotent = self::IS_IDEMPOTENT,
    ): UpdateTransaction {
        return new UpdateTransaction(
            self::TABLE_NAME,
            self::IDENTIFIER_COLUMN,
            self::IDENTIFIER_COLUMN_TYPE,
            $identifiers,
            $columnsWithValuesForUpdate,
            $columnTypes,
            $isIdempotent
        );
    }
}
