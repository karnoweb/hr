<?php

namespace Karnoweb\Hr\Support;

use Illuminate\Database\QueryException;

/**
 * Classifies database query exceptions for domain-level handling.
 */
final class QueryExceptionClassifier
{
    public static function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        $message = strtolower($e->getMessage());

        // SQLSTATE 23000 / SQLite 19 / MySQL 1062 / Postgres unique_violation
        return $sqlState === '23000'
            || $sqlState === '23505'
            || $driverCode === 19
            || $driverCode === 1062
            || str_contains($message, 'unique')
            || str_contains($message, 'duplicate');
    }
}
