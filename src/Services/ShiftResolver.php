<?php

namespace Karnoweb\Hr\Services;

use Illuminate\Support\Carbon;
use Karnoweb\Hr\Models\Employee;
use Karnoweb\Hr\Models\EmployeeShiftAssignment;
use Karnoweb\Hr\Models\Shift;
use Karnoweb\Hr\Models\ShiftPattern;

/**
 * Resolves which shift an employee works on a given calendar date (HR-036).
 */
class ShiftResolver
{
    /**
     * Return the shift for the employee on the date, or null for day-off / unassigned.
     */
    public function resolve(Employee $employee, Carbon $date): ?Shift
    {
        $assignment = $this->assignmentForDate($employee, $date);

        if ($assignment === null) {
            return null;
        }

        if ($assignment->shift_id !== null) {
            /** @var Shift|null $shift */
            $shift = $assignment->shift;

            return $shift;
        }

        if ($assignment->shift_pattern_id !== null) {
            return $this->resolveFromPattern($assignment, $date);
        }

        return null;
    }

    public function assignmentForDate(Employee $employee, Carbon $date): ?EmployeeShiftAssignment
    {
        $dateString = $date->copy()->startOfDay()->toDateString();

        return EmployeeShiftAssignment::query()
            ->where('employee_id', $employee->id)
            ->whereDate('effective_date', '<=', $dateString)
            ->where(function ($query) use ($dateString) {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $dateString);
            })
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();
    }

    protected function resolveFromPattern(EmployeeShiftAssignment $assignment, Carbon $date): ?Shift
    {
        /** @var ShiftPattern|null $pattern */
        $pattern = $assignment->shiftPattern;

        if ($pattern === null) {
            return null;
        }

        $patternStart = $assignment->pattern_start_date ?? $assignment->effective_date;

        if ($patternStart === null) {
            return null;
        }

        $daysSinceStart = (int) $patternStart->copy()->startOfDay()->diffInDays($date->copy()->startOfDay(), false);

        if ($daysSinceStart < 0) {
            return null;
        }

        $dayInCycle = $daysSinceStart % max(1, $pattern->cycle_days);

        $patternItem = collect($pattern->pattern)->first(
            fn ($item) => is_array($item) && (int) ($item['day'] ?? -1) === $dayInCycle
        );

        if (! is_array($patternItem) || empty($patternItem['shift_id'])) {
            return null;
        }

        return Shift::query()->find($patternItem['shift_id']);
    }
}
