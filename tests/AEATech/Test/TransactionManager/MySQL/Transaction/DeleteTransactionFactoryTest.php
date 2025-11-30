<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\Transaction\DeleteTransaction;
use AEATech\TransactionManager\MySQL\Transaction\DeleteTransactionFactory;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeleteTransactionFactory::class)]
class DeleteTransactionFactoryTest extends TestCase
{
    private const TABLE_NAME = 'tm_delete_test';
    private const IDENTIFIER_COLUMN = 'identifier_column';
    private const IDENTIFIER_COLUMN_TYPE = ParameterType::INTEGER;
    private const IDENTIFIERS = [1, 2, 3];
    private const IS_IDEMPOTENT = false;

    #[Test]
    public function factory(): void
    {
        $expected = new DeleteTransaction(
            self::TABLE_NAME,
            self::IDENTIFIER_COLUMN,
            self::IDENTIFIER_COLUMN_TYPE,
            self::IDENTIFIERS,
            self::IS_IDEMPOTENT
        );

        $actual = (new DeleteTransactionFactory())->factory(
            self::TABLE_NAME,
            self::IDENTIFIER_COLUMN,
            self::IDENTIFIER_COLUMN_TYPE,
            self::IDENTIFIERS,
            self::IS_IDEMPOTENT
        );

        self::assertEquals($expected, $actual);
    }
}
