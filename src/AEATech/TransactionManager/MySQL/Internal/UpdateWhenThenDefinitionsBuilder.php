<?php
declare(strict_types=1);

namespace AEATech\TransactionManager\MySQL\Internal;

use InvalidArgumentException;

/**
 * @internal
 */
class UpdateWhenThenDefinitionsBuilder
{
    public const MESSAGE_ROWS_MUST_NOT_BE_EMPTY = 'Rows must not be empty.';
    public const MESSAGE_UPDATE_COLUMNS_MUST_NOT_BE_EMPTY = 'Update columns must not be empty.';

    public function build(
        array $rows,
        string $identifierColumn,
        array $updateColumns,
    ): array {
        if (empty($rows)) {
            throw new InvalidArgumentException(self::MESSAGE_ROWS_MUST_NOT_BE_EMPTY);
        }

        if (empty($updateColumns)) {
            throw new InvalidArgumentException(self::MESSAGE_UPDATE_COLUMNS_MUST_NOT_BE_EMPTY);
        }

        $identifiers = [];
        $updateDefinitions = [];

        foreach ($rows as $index =>  $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException(self::buildMessageInvalidRowType($index, $row));
            }

            if (
                false === isset($row[$identifierColumn])
                && false === array_key_exists($identifierColumn, $row)
            ) {
                throw new InvalidArgumentException(self::buildMessageMissingColumnValue(
                    $index,
                    $identifierColumn
                ));
            }

            $identifiers[] = $row[$identifierColumn];

            foreach ($updateColumns as $column) {
                if (false === isset($row[$column]) && false === array_key_exists($column, $row)) {
                    throw new InvalidArgumentException(self::buildMessageMissingColumnValue(
                        $index,
                        $column
                    ));
                }

                $updateDefinitions[$column][] = [
                    $row[$identifierColumn],
                    $row[$column],
                ];
            }
        }

        return [
            $identifiers,
            $updateDefinitions,
        ];
    }

    public static function buildMessageInvalidRowType(int|string $index, mixed $row): string
    {
        return sprintf('Row "%s" must be an array, "%s" given', $index, get_debug_type($row));
    }

    public static function buildMessageMissingColumnValue(int|string $index, int|string $column): string
    {
        return sprintf('Missing column "%s" in row "%s".', $index, $column);
    }
}
