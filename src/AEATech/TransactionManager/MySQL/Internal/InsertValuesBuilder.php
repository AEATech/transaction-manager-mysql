<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL\Internal;

use InvalidArgumentException;

/**
 * @internal
 */
class InsertValuesBuilder
{
    /**
     * @param array<array<string,mixed>> $rows
     * @param array<string,int|string>   $columnTypes
     *
     * @return array{
     *     0: string,                  // VALUES (...), (...), ...
     *     1: array<int,mixed>,        // params
     *     2: array<int,int|string>,   // types
     *     3: array<int,string>        // columns
     * }
     *
     * @throws InvalidArgumentException
     */
    public function build(array $rows, array $columnTypes): array
    {
        if ([] === $rows) {
            throw new InvalidArgumentException('Insert requires non-empty $rows.');
        }

        $firstRow = reset($rows);

        if (false === is_array($firstRow) || [] === $firstRow) {
            throw new InvalidArgumentException('Insert: first row must be a non-empty array.');
        }

        $columns = array_keys($firstRow);  // the first row fixes the order of columns

        $valuesSqlParts = [];
        $params = [];
        $types = [];

        $paramIndex = 0;

        foreach ($rows as $rowIndex => $row) {
            if (false === is_array($row)) {
                throw new InvalidArgumentException(sprintf(
                    'Insert: row %s must be an array, %s given',
                    $rowIndex,
                    get_debug_type($row)
                ));
            }

            $placeholders = [];

            foreach ($columns as $column) {
                if (false === isset($row[$column]) && false === array_key_exists($column, $row)) {
                    throw new InvalidArgumentException(sprintf(
                        'Insert: row %s is missing required column "%s".',
                        $rowIndex,
                        $column
                    ));
                }

                $placeholders[] = '?';
                $params[] = $row[$column];

                // If a type is defined for this column, use it.
                // If a type is not defined — do not write to $types,
                // Doctrine will bind the parameter as ParameterType::STRING.
                if (isset($columnTypes[$column])) {
                    $types[$paramIndex] = $columnTypes[$column];
                }

                $paramIndex++;
            }

            $valuesSqlParts[] = '(' . implode(', ', $placeholders) . ')';
        }

        $valuesSql = implode(', ', $valuesSqlParts);

        return [
            $valuesSql,
            $params,
            $types, // maybe partially/fully empty → everything without a type is bound as STRING
            $columns
        ];
    }
}
