<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\Internal\UpdateWhenThenDefinitionsBuilder;
use AEATech\TransactionManager\MySQL\Transaction\UpdateWhenThenTransaction;
use AEATech\TransactionManager\MySQL\Transaction\UpdateWhenThenTransactionFactory;
use Doctrine\DBAL\ParameterType;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateWhenThenTransactionFactory::class)]
class UpdateWhenThenTransactionFactoryTest extends TestCase
{
    private const COLUMN_1 = 'column_1';
    private const COLUMN_2 = 'column_2';
    private const TABLE_NAME = 'tm_update_test';
    private const ROWS = [
        [
            self::IDENTIFIER_COLUMN => 1,
            self::COLUMN_1 => '10',
            self::COLUMN_2 => 100,
        ],
        [
            self::IDENTIFIER_COLUMN => 2,
            self::COLUMN_1 => '20',
            self::COLUMN_2 => 200,
        ],
    ];
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

    private MockInterface & UpdateWhenThenDefinitionsBuilder $updateWhenThenDefinitionsBuilder;

    private UpdateWhenThenTransactionFactory $transactionFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->updateWhenThenDefinitionsBuilder = Mockery::mock(UpdateWhenThenDefinitionsBuilder::class);

        $this->transactionFactory = new UpdateWhenThenTransactionFactory($this->updateWhenThenDefinitionsBuilder);
    }

    #[Test]
    public function factory(): void
    {
        $expected = new UpdateWhenThenTransaction(
            $this->updateWhenThenDefinitionsBuilder,
            self::TABLE_NAME,
            self::ROWS,
            self::IDENTIFIER_COLUMN,
            self::IDENTIFIER_COLUMN_TYPE,
            self::UPDATE_COLUMNS,
            self::UPDATE_COLUMN_TYPES,
            self::IS_IDEMPOTENT,
        );

        $actual = $this->transactionFactory->factory(
            self::TABLE_NAME,
            self::ROWS,
            self::IDENTIFIER_COLUMN,
            self::IDENTIFIER_COLUMN_TYPE,
            self::UPDATE_COLUMNS,
            self::UPDATE_COLUMN_TYPES,
            self::IS_IDEMPOTENT,
        );

        self::assertEquals($expected, $actual);
    }
}
