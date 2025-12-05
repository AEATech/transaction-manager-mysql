<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\Internal\UpdateWhenThenDefinitionsBuilder;
use AEATech\TransactionManager\MySQL\Transaction\UpdateWhenThenTransaction;
use AEATech\TransactionManager\Query;
use Doctrine\DBAL\ParameterType;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateWhenThenTransaction::class)]
class UpdateWhenThenTransactionTest extends TestCase
{
    private const COLUMN_1 = 'column_1';
    private const COLUMN_2 = 'column_2';
    private const TABLE_NAME = 'tm_update_test';
    private const IDENTIFIER_COLUMN = 'identifier_column';
    private const IDENTIFIER_COLUMN_TYPE = ParameterType::INTEGER;
    private const UPDATE_COLUMNS = [
        self::COLUMN_1,
        self::COLUMN_2,
    ];
    private const UPDATE_COLUMN_TYPES = [
        self::COLUMN_1 => ParameterType::STRING,
        self::COLUMN_2 => ParameterType::INTEGER,
    ];
    private const IS_IDEMPOTENT = false;

    private const IDENTIFIER_1 = 1;
    private const COLUMN_1_1 = 'value 1';
    private const COLUMN_2_1 = 100501;
    private const IDENTIFIER_2 = 2;
    private const COLUMN_1_2 = 'value 2';
    private const COLUMN_2_2 = 100502;

    private const ROWS = [
        [
            self::IDENTIFIER_COLUMN => self::IDENTIFIER_1,
            self::COLUMN_1 => self::COLUMN_1_1,
            self::COLUMN_2 => self::COLUMN_2_1,
        ],
        [
            self::IDENTIFIER_COLUMN => self::IDENTIFIER_2,
            self::COLUMN_1 => self::COLUMN_1_2,
            self::COLUMN_2 => self::COLUMN_2_2,
        ],
    ];

    private const DEFINITIONS_BUILDER_RESULT = [
        [
            self::IDENTIFIER_1,
            self::IDENTIFIER_2,
        ],
        [
            self::COLUMN_1 => [
                [self::IDENTIFIER_1, self::COLUMN_1_1],
                [self::IDENTIFIER_2, self::COLUMN_1_2],
            ],
            self::COLUMN_2 => [
                [self::IDENTIFIER_1, self::COLUMN_2_1],
                [self::IDENTIFIER_2, self::COLUMN_2_2],
            ],
        ],
    ];

    private const EXPECTED_SQL
        = 'UPDATE `' . self::TABLE_NAME .'` ' .
          'SET `'
            . self::COLUMN_1 . '` = CASE ' .
                'WHEN `' . self::IDENTIFIER_COLUMN . '` = ? THEN ? ' .
                'WHEN `' . self::IDENTIFIER_COLUMN . '` = ? THEN ? ' .
                'ELSE `' . self::COLUMN_1 . '` END, `'
            . self::COLUMN_2 . '` = CASE ' .
                'WHEN `' . self::IDENTIFIER_COLUMN . '` = ? THEN ? ' .
                'WHEN `' . self::IDENTIFIER_COLUMN . '` = ? THEN ? ' .
                'ELSE `' . self::COLUMN_2 . '` END ' .
          'WHERE `' . self::IDENTIFIER_COLUMN . '` IN (?, ?)';

    private const EXPECTED_PARAMS = [
        self::IDENTIFIER_1,
        self::COLUMN_1_1,
        self::IDENTIFIER_2,
        self::COLUMN_1_2,
        self::IDENTIFIER_1,
        self::COLUMN_2_1,
        self::IDENTIFIER_2,
        self::COLUMN_2_2,
        self::IDENTIFIER_1,
        self::IDENTIFIER_2,
    ];

    private MockInterface & UpdateWhenThenDefinitionsBuilder $definitionsBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->definitionsBuilder = Mockery::mock(UpdateWhenThenDefinitionsBuilder::class);
    }

    #[Test]
    #[DataProvider('buildDataProvider')]
    public function build(array $updateColumnTypes): void
    {
        $this->definitionsBuilder
            ->shouldReceive('build')
            ->once()
            ->with(self::ROWS, self::IDENTIFIER_COLUMN, self::UPDATE_COLUMNS)
            ->andReturn(self::DEFINITIONS_BUILDER_RESULT);

        // Collect when then types
        $expectedTypes = [];
        $typesIndex = 0;
        foreach (self::DEFINITIONS_BUILDER_RESULT[1] as $column => $values) {
            foreach ($values as $notUsed) {
                $expectedTypes[$typesIndex] = self::IDENTIFIER_COLUMN_TYPE;
                $typesIndex++;

                if (isset($updateColumnTypes[$column])) {
                    $expectedTypes[$typesIndex] = $updateColumnTypes[$column];
                }
                $typesIndex++;
            }
        }

        // append identifiers types
        $expectedTypes += array_fill($typesIndex, count(self::ROWS), ParameterType::INTEGER);

        $expected = new Query(self::EXPECTED_SQL, self::EXPECTED_PARAMS, $expectedTypes);

        $transaction = self::createTransaction(
            definitionsBuilder: $this->definitionsBuilder,
            updateColumnTypes: $updateColumnTypes
        );

        /** @noinspection PhpUnhandledExceptionInspection */
        $actual = $transaction->build();

        self::assertEquals($expected, $actual);
    }

    public static function buildDataProvider(): array
    {
        return [
            [
                'updateColumnTypes' => self::UPDATE_COLUMN_TYPES,
            ],
            [
                'updateColumnTypes' => [
                    self::COLUMN_1 => ParameterType::STRING,
                ],
            ],
            [
                'updateColumnTypes' => [
                    self::COLUMN_2 => ParameterType::INTEGER,
                ],
            ],
        ];
    }

    #[Test]
    #[DataProvider('isIdempotentDataProvider')]
    public function isIdempotent(bool $isIdempotent): void
    {
        $transaction = self::createTransaction(
            definitionsBuilder: $this->definitionsBuilder,
            isIdempotent: $isIdempotent
        );

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

    private static function createTransaction(
        UpdateWhenThenDefinitionsBuilder $definitionsBuilder,
        array $updateColumnTypes = self::UPDATE_COLUMN_TYPES,
        bool $isIdempotent = self::IS_IDEMPOTENT,
    ): UpdateWhenThenTransaction {
        return new UpdateWhenThenTransaction(
            $definitionsBuilder,
            self::TABLE_NAME,
            self::ROWS,
            self::IDENTIFIER_COLUMN,
            self::IDENTIFIER_COLUMN_TYPE,
            self::UPDATE_COLUMNS,
            $updateColumnTypes,
            $isIdempotent
        );
    }
}
