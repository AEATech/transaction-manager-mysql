<?php
declare(strict_types=1);

namespace AEATech\Test\TransactionManager\MySQL;

use AEATech\TransactionManager\MySQL\Transaction\InsertTransaction;
use Doctrine\DBAL\ParameterType;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(InsertTransaction::class)]
class InsertTransactionTest extends TestCase
{
    /**
     * @throws Throwable
     */
    #[Test]
    public function buildGeneratesExpectedSqlParamsAndTypesWithMultipleRows(): void
    {
        // Note: column name contains a backtick to verify escaping by quoteIdentifier
        $rows = [
            ['id' => 1, 'na`me' => 'Alex', 'age' => 30],
            ['id' => 2, 'na`me' => 'Bob',  'age' => 25],
        ];

        // Only some columns have explicit types; others should be omitted from types map
        $types = [
            'id' => ParameterType::INTEGER,
            'age' => ParameterType::INTEGER,
            // 'na`me' intentionally omitted
        ];

        $tx = new InsertTransaction('users', $rows, $types, isIdempotent: true);

        $q = $tx->build();

        $expectedSql = <<<'SQL'
INSERT INTO `users` (`id`, `na``me`, `age`) VALUES (?, ?, ?), (?, ?, ?)
SQL;

        self::assertSame($expectedSql, $q->sql);
        self::assertSame([1, 'Alex', 30, 2, 'Bob', 25], $q->params);

        // Types are indexed by parameter positions; only provided columns appear
        // id: positions 0 and 3; age: positions 2 and 5
        self::assertSame([
            0 => ParameterType::INTEGER,
            2 => ParameterType::INTEGER,
            3 => ParameterType::INTEGER,
            5 => ParameterType::INTEGER,
        ], $q->types);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function respectsColumnsOrderFromFirstRow(): void
    {
        $rows = [
            ['a' => 'A1', 'b' => 'B1', 'c' => 'C1'], // defines order a,b,c
            // different key order in second row must not affect SQL/params order
            ['c' => 'C2', 'a' => 'A2', 'b' => 'B2'],
        ];

        $tx = new InsertTransaction('tab', $rows);
        $q = $tx->build();

        $expectedSql = <<<'SQL'
INSERT INTO `tab` (`a`, `b`, `c`) VALUES (?, ?, ?), (?, ?, ?)
SQL;

        self::assertSame($expectedSql, $q->sql);
        self::assertSame(['A1', 'B1', 'C1', 'A2', 'B2', 'C2'], $q->params);
        self::assertSame([], $q->types, 'No explicit types means empty map');
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function missingRequiredColumnInRowThrows(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'A'],
            // missing "name" column
            ['id' => 2],
        ];

        $tx = new InsertTransaction('t', $rows);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing required column "name"');
        $tx->build();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function nonArrayRowThrowsWithTypeInMessage(): void
    {
        $rows = [
            ['x' => 1],
            'oops', // row 1 is not an array
        ];

        $tx = new InsertTransaction('t', $rows);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('row 1 must be an array, string given');
        $tx->build();
    }

    #[Test]
    public function constructorRejectsEmptyRows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires non-empty $rows');
        new InsertTransaction('t', []);
    }

    #[Test]
    public function constructorRejectsEmptyFirstRow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('first row must be a non-empty array');
        new InsertTransaction('t', [[]]);
    }

    #[Test]
    public function constructorRejectsNonArrayFirstRow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('first row must be a non-empty array');

        /** @noinspection PhpParamsInspection */
        new InsertTransaction('t', [123]);
    }

    #[Test]
    #[DataProvider('isIdempotentReflectsFlagDataProvider')]
    public function isIdempotentReflectsFlag(bool $isIdempotent): void
    {
        self::assertSame(
            $isIdempotent,
            (new InsertTransaction('t', [['a' => 1]], isIdempotent: $isIdempotent))->isIdempotent()
        );
    }

    public static function isIdempotentReflectsFlagDataProvider(): array
    {
        return [
            [
                'isIdempotent' => true,
            ],
            [
                'isIdempotent' => false,
            ]
        ];
    }
}
