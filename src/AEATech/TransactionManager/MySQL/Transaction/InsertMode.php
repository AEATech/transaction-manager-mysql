<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL\Transaction;

enum InsertMode
{
    case Regular; // INSERT INTO
    case Ignore;  // INSERT IGNORE INTO
}
