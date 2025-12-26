<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL;

use AEATech\TransactionManager\MySQL\Transaction\DeleteWithLimitTransactionFactory;
use AEATech\TransactionManager\MySQL\Transaction\InsertIgnoreTransactionFactory;
use AEATech\TransactionManager\MySQL\Transaction\InsertOnDuplicateKeyUpdateTransactionFactory;
use AEATech\TransactionManager\Transaction\DeleteTransactionFactory;
use AEATech\TransactionManager\Transaction\InsertTransactionFactory;
use AEATech\TransactionManager\Transaction\Internal\InsertValuesBuilder;
use AEATech\TransactionManager\Transaction\Internal\UpdateWhenThenDefinitionsBuilder;
use AEATech\TransactionManager\Transaction\UpdateTransactionFactory;
use AEATech\TransactionManager\Transaction\UpdateWhenThenTransactionFactory;

class MySQLTransactionsFactoryBuilder
{
    public static function build(): MySQLTransactionsFactoryInterface
    {
        $quoter = new MySQLIdentifierQuoter();
        $insertValuesBuilder = new InsertValuesBuilder();
        $updateWhenThenDefinitionsBuilder = new UpdateWhenThenDefinitionsBuilder();

        return new MySQLTransactionsFactory(
            insertTransactionFactory: new InsertTransactionFactory($insertValuesBuilder, $quoter),
            insertIgnoreTransactionFactory: new InsertIgnoreTransactionFactory($insertValuesBuilder, $quoter),
            insertOnDuplicateKeyUpdateTransactionFactory: new InsertOnDuplicateKeyUpdateTransactionFactory(
                $insertValuesBuilder,
                $quoter
            ),
            deleteTransactionFactory: new DeleteTransactionFactory($quoter),
            deleteWithLimitTransactionFactory: new DeleteWithLimitTransactionFactory($quoter),
            updateTransactionFactory: new UpdateTransactionFactory($quoter),
            updateWhenThenTransactionFactory: new UpdateWhenThenTransactionFactory(
                $updateWhenThenDefinitionsBuilder,
                $quoter
            ),
        );
    }
}
