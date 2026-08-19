<?php

namespace Karnoweb\Hr\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Atomic numeric sequence allocator.
 *
 * Backed by the `hr_sequences` table. Use a stable, namespaced `$scope`
 * string (e.g. `employee_code:1404` or `document:hire:2026`) so each
 * domain/year/type sequence stays independent.
 *
 * This is the single sequence mechanism for the package — employee codes
 * (Phase 1) and document numbers (Phase 10) must call this service rather
 * than rolling their own max/count + 1 logic.
 */
class SequenceGenerator
{
    public function nextValue(string $scope): int
    {
        $scope = trim($scope);

        if ($scope === '') {
            throw new InvalidArgumentException('Sequence scope must not be empty.');
        }

        return (int) DB::transaction(function () use ($scope) {
            $this->ensureScopeRowExists($scope);

            $row = DB::table($this->table())
                ->where('scope', $scope)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                throw new RuntimeException("Sequence scope [{$scope}] is missing after allocation.");
            }

            $next = (int) $row->last_value + 1;

            DB::table($this->table())
                ->where('scope', $scope)
                ->update([
                    'last_value' => $next,
                    'updated_at' => now(),
                ]);

            return $next;
        });
    }

    /**
     * Peek at the current value without allocating the next one.
     * Returns 0 when the scope has never been used.
     */
    public function currentValue(string $scope): int
    {
        $row = DB::table($this->table())
            ->where('scope', $scope)
            ->first();

        return $row === null ? 0 : (int) $row->last_value;
    }

    /**
     * Insert a zeroed row for the scope if missing. Concurrent first-writers
     * may race on the unique `scope` index; the loser simply continues and
     * locks the row the winner created.
     */
    protected function ensureScopeRowExists(string $scope): void
    {
        $exists = DB::table($this->table())
            ->where('scope', $scope)
            ->lockForUpdate()
            ->exists();

        if ($exists) {
            return;
        }

        try {
            DB::table($this->table())->insert([
                'scope' => $scope,
                'last_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $e) {
            if (! QueryExceptionClassifier::isUniqueViolation($e)) {
                throw $e;
            }
        }
    }

    protected function table(): string
    {
        return config('hr.tables.prefix', 'hr_').'sequences';
    }
}
