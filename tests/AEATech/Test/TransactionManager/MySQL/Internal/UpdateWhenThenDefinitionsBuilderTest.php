<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL\Internal;

use AEATech\TransactionManager\MySQL\Internal\UpdateWhenThenDefinitionsBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateWhenThenDefinitionsBuilder::class)]
class UpdateWhenThenDefinitionsBuilderTest extends TestCase
{
    private const IDENTIFIER_COLUMN = 'identifier_column';
    private const COLUMN_1 = 'column_1';
    private const COLUMN_2 = 'column_2';
    private const UPDATE_COLUMNS = [
        self::COLUMN_1,
        self::COLUMN_2,
    ];

    private const ROWS = [
        [
            self::IDENTIFIER_COLUMN => 1,
            self::COLUMN_1 => 'value 1',
            self::COLUMN_2 => 100501,
            'some_column' => 'some value 1',
        ],
        [
            self::IDENTIFIER_COLUMN => 2,
            self::COLUMN_1 => 'value 2',
            self::COLUMN_2 => 100502,
            'some_column' => 'some value 2',
        ],
    ];

    private UpdateWhenThenDefinitionsBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new UpdateWhenThenDefinitionsBuilder();
    }

    #[Test]
    public function build(): void
    {
        $identifiers = [];
        $updateDefinitions = [];

        foreach (self::ROWS as $row) {
            [
                self::IDENTIFIER_COLUMN => $identifier,
                self::COLUMN_1 => $value1,
                self::COLUMN_2 => $value2,
            ] = $row;

            $identifiers[] = $identifier;
            $updateDefinitions[self::COLUMN_1][] = [$identifier, $value1];
            $updateDefinitions[self::COLUMN_2][] = [$identifier, $value2];
        }

        $expected = [$identifiers, $updateDefinitions];
        $actual = $this->builder->build(
            self::ROWS,
            self::IDENTIFIER_COLUMN,
            self::UPDATE_COLUMNS
        );

        self::assertSame($expected, $actual);
    }

    #[Test]
    public function buildFailedWithEmptyRows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(UpdateWhenThenDefinitionsBuilder::MESSAGE_ROWS_MUST_NOT_BE_EMPTY);

        $this->builder->build(
            [],
            self::IDENTIFIER_COLUMN,
            self::UPDATE_COLUMNS
        );
    }

    #[Test]
    public function buildFailedWithEmptyUpdateColumns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(UpdateWhenThenDefinitionsBuilder::MESSAGE_UPDATE_COLUMNS_MUST_NOT_BE_EMPTY);

        $this->builder->build(
            self::ROWS,
            self::IDENTIFIER_COLUMN,
            []
        );
    }

    #[Test]
    public function buildFailedWithInvalidRowType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(UpdateWhenThenDefinitionsBuilder::buildMessageInvalidRowType(1, 'string'));

        $rows = [
            [
                self::IDENTIFIER_COLUMN => 1,
                self::COLUMN_1 => 'value 1',
                self::COLUMN_2 => 100501,
            ],

            'invalid',

            [
                self::IDENTIFIER_COLUMN => 2,
                self::COLUMN_1 => 'value 2',
                self::COLUMN_2 => 100502,
            ],
        ];

        $this->builder->build(
            $rows,
            self::IDENTIFIER_COLUMN,
            self::UPDATE_COLUMNS
        );
    }

    #[Test]
    #[DataProvider('buildMessageMissingColumnValueDataProvider')]
    public function buildFailedWithMissingColumnValue(array $rows, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->builder->build(
            $rows,
            self::IDENTIFIER_COLUMN,
            self::UPDATE_COLUMNS
        );
    }

    public static function buildMessageMissingColumnValueDataProvider(): array
    {
        return [
            [
                'rows' => [
                    [
                        self::IDENTIFIER_COLUMN => 1,
                        self::COLUMN_1 => 'value 1',
                        self::COLUMN_2 => 100501,
                    ],
                    [
                        self::COLUMN_1 => 'value 2',
                        self::COLUMN_2 => 100502,
                    ],
                ],
                'message' => UpdateWhenThenDefinitionsBuilder::buildMessageMissingColumnValue(
                    1,
                    self::IDENTIFIER_COLUMN
                ),
            ],
            [
                'rows' => [
                    [
                        self::IDENTIFIER_COLUMN => 1,
                        self::COLUMN_1 => 'value 1',
                        self::COLUMN_2 => 100501,
                    ],
                    [
                        self::IDENTIFIER_COLUMN => 2,
                        self::COLUMN_2 => 100502,
                    ],
                ],
                'message' => UpdateWhenThenDefinitionsBuilder::buildMessageMissingColumnValue(
                    1,
                    self::COLUMN_1
                ),
            ],
        ];
    }
}
