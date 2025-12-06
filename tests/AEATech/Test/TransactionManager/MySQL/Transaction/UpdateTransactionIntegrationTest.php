<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\MySQLIdentifierQuoter;
use AEATech\TransactionManager\Transaction\UpdateTransaction;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

#[Group('integration')]
#[CoversClass(UpdateTransaction::class)]
class UpdateTransactionIntegrationTest extends AbstractUpdateTransactionIntegrationTestCase
{
    private const COLUMNS_WITH_VALUES_FOR_UPDATE = [
        self::UPDATE_COLUMN_1 => 'updated value',
        self::UPDATE_COLUMN_2 => 200500,
    ];

    /**
     * @throws Throwable
     */
    #[Test]
    public function updateSuccessfully(): void
    {
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
            new MySQLIdentifierQuoter(),
            self::TABLE_NAME,
            self::IDENTIFIER_COLUMN,
            ParameterType::INTEGER,
            $identifiers,
            self::COLUMNS_WITH_VALUES_FOR_UPDATE,
            self::COLUMN_TYPES,
        );
        $affectedRows = $this->runTransaction($updateTransaction);
        self::assertSame(count($identifiers), $affectedRows);

        self::assertState($expected);
    }
}
