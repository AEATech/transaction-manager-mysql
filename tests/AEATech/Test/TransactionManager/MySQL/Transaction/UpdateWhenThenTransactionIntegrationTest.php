<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL\Transaction;

use AEATech\TransactionManager\MySQL\Internal\UpdateWhenThenDefinitionsBuilder;
use AEATech\TransactionManager\MySQL\Transaction\UpdateWhenThenTransaction;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

#[Group('integration')]
#[CoversClass(UpdateWhenThenTransaction::class)]
class UpdateWhenThenTransactionIntegrationTest extends AbstractUpdateTransactionIntegrationTestCase
{
    private const UPDATE_COLUMNS = [
        self::UPDATE_COLUMN_1,
        self::UPDATE_COLUMN_2,
    ];

    /**
     * @throws Throwable
     */
    #[Test]
    public function updateSuccessfully(): void
    {
        $rows = [];
        $expected = [];
        foreach (self::INIT_STATE as $index => $item) {
            if ($index % 2 === 0) {
                $item[self::UPDATE_COLUMN_1] = 'updated ' . $item[self::UPDATE_COLUMN_1];
                $item[self::UPDATE_COLUMN_2] = 2 * $item[self::UPDATE_COLUMN_2];
                $rows[] = $item;
            }

            $expected[] = $item;
        }

        $updateTransaction = new UpdateWhenThenTransaction(
            new UpdateWhenThenDefinitionsBuilder(),
            self::TABLE_NAME,
            $rows,
            self::IDENTIFIER_COLUMN,
            ParameterType::INTEGER,
            self::UPDATE_COLUMNS,
            self::COLUMN_TYPES,
        );
        $affectedRows = $this->runTransaction($updateTransaction);
        self::assertSame(count($rows), $affectedRows);

        self::assertState($expected);
    }
}
