<?php

namespace Karnoweb\Hr\Services;

use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Karnoweb\Hr\Exceptions\DuplicateActiveRecordException;
use Karnoweb\Hr\Models\Employee;
use Karnoweb\Hr\Models\EmployeeShiftAssignment;
use Karnoweb\Hr\Models\Shift;
use Karnoweb\Hr\Models\ShiftPattern;
use Karnoweb\Hr\Support\QueryExceptionClassifier;

/**
 * Assigns fixed shifts or rotating patterns to employees (HR-039).
 */
class ShiftAssignmentService
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function assignShift(
        Employee $employee,
        int $shiftId,
        DateTimeInterface|string|null $effectiveDate = null,
        array $options = []
    ): EmployeeShiftAssignment {
        Shift::query()->whereKey($shiftId)->firstOrFail();

        return $this->assign($employee, [
            'shift_id' => $shiftId,
            'shift_pattern_id' => null,
            'pattern_start_date' => null,
        ], $effectiveDate, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function assignPattern(
        Employee $employee,
        int $patternId,
        DateTimeInterface|string|null $effectiveDate = null,
        DateTimeInterface|string|null $patternStartDate = null,
        array $options = []
    ): EmployeeShiftAssignment {
        ShiftPattern::query()->whereKey($patternId)->firstOrFail();

        $effective = $effectiveDate
            ? Carbon::parse($effectiveDate)->startOfDay()
            : Carbon::now()->startOfDay();

        $patternStart = $patternStartDate
            ? Carbon::parse($patternStartDate)->startOfDay()
            : $effective->copy();

        return $this->assign($employee, [
            'shift_id' => null,
            'shift_pattern_id' => $patternId,
            'pattern_start_date' => $patternStart->toDateString(),
        ], $effective, $options);
    }

    /**
     * @param  array{shift_id?: int|null, shift_pattern_id?: int|null, pattern_start_date?: string|null}  $payload
     * @param  array<string, mixed>  $options
     */
    protected function assign(
        Employee $employee,
        array $payload,
        DateTimeInterface|string|null $effectiveDate,
        array $options = []
    ): EmployeeShiftAssignment {
        $effective = $effectiveDate
            ? Carbon::parse($effectiveDate)->startOfDay()
            : Carbon::now()->startOfDay();

        if ($payload['shift_id'] === null && $payload['shift_pattern_id'] === null) {
            throw new InvalidArgumentException('Either shift_id or shift_pattern_id is required.');
        }

        if ($payload['shift_id'] !== null && $payload['shift_pattern_id'] !== null) {
            throw new InvalidArgumentException('Cannot assign both a fixed shift and a shift pattern.');
        }

        return DB::transaction(function () use ($employee, $payload, $effective) {
            $employee = Employee::query()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();

            $this->closeCurrentAssignment($employee, $effective->copy()->subDay());

            try {
                return EmployeeShiftAssignment::query()->create([
                    'employee_id' => $employee->id,
                    'shift_id' => $payload['shift_id'],
                    'shift_pattern_id' => $payload['shift_pattern_id'],
                    'effective_date' => $effective->toDateString(),
                    'end_date' => null,
                    'pattern_start_date' => $payload['pattern_start_date'],
                    'is_active' => true,
                    'current_key' => $employee->id,
                ]);
            } catch (QueryException $e) {
                if (QueryExceptionClassifier::isUniqueViolation($e)) {
                    throw new DuplicateActiveRecordException(
                        'Could not assign shift: employee already has an active shift assignment.',
                        previous: $e
                    );
                }

                throw $e;
            }
        });
    }

    protected function closeCurrentAssignment(Employee $employee, Carbon $closeDate): void
    {
        $current = EmployeeShiftAssignment::query()
            ->where('employee_id', $employee->id)
            ->whereNotNull('current_key')
            ->lockForUpdate()
            ->first();

        if ($current === null) {
            return;
        }

        $current->update([
            'end_date' => $closeDate->toDateString(),
            'is_active' => false,
            'current_key' => null,
        ]);
    }
}
