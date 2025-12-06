<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL;

use AEATech\TransactionManager\MySQL\MySQLIdentifierQuoter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MySQLIdentifierQuoter::class)]
class MySQLIdentifierQuoterTest extends TestCase
{
    #[Test]
    #[DataProvider('quoteIdentifierDataProvider')]
    public function quoteIdentifier(string $value, string $expected): void
    {
        self::assertSame($expected, (new MySQLIdentifierQuoter())->quoteIdentifier($value));
    }

    public static function quoteIdentifierDataProvider(): array
    {
        return [
            [
                'value' => 'some_identifier',
                'expected' => '`some_identifier`',
            ],
            [
                'value' => 'some_`identifier',
                'expected' => '`some_``identifier`',
            ],
        ];
    }

    #[Test]
    public function quoteIdentifiers(): void
    {
        $identifiers = ['some_identifier', 'some_`identifier'];
        $expected = ['`some_identifier`', '`some_``identifier`'];

        self::assertSame($expected, (new MySQLIdentifierQuoter())->quoteIdentifiers($identifiers));
    }
}
