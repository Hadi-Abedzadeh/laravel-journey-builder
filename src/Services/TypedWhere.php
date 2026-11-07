<?php

namespace OpenJourney\LaravelJourneyBuilder\Services;

use Illuminate\Support\Facades\DB;

/**
 * Bind VARCHAR-compatible expressions on SQL Server without casting indexed columns.
 * Column names and lengths are hard-coded by trusted package code; never user inputs.
 */
final class TypedWhere
{
    public static function eq($query, string $column, string $value, int $length): mixed
    {
        if (DB::connection(config('journey_builder.connection'))->getDriverName() !== 'sqlsrv') {
            return $query->where($column, $value);
        }
        return $query->whereRaw(self::quote($column).' = CAST(? AS varchar('.$length.'))', [$value]);
    }

    public static function oneOf($query, string $column, array $values, int $length): mixed
    {
        if (DB::connection(config('journey_builder.connection'))->getDriverName() !== 'sqlsrv') {
            return $query->whereIn($column, $values);
        }
        if ($values === []) return $query->whereRaw('1 = 0');
        $marks = implode(', ', array_fill(0, count($values), 'CAST(? AS varchar('.$length.'))'));
        return $query->whereRaw(self::quote($column).' IN ('.$marks.')', $values);
    }

    private static function quote(string $column): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/D', $column)) {
            throw new \InvalidArgumentException('Unsafe column reference');
        }
        return implode('.', array_map(fn($part) => '['.$part.']', explode('.', $column)));
    }
}
