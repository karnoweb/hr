<?php

namespace Karnoweb\Hr\Services;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Hr\Enums\EmployeeStatus;
use Karnoweb\Hr\Models\Employee;
use Karnoweb\Hr\Models\EmployeePosition;

/**
 * Service for employee lifecycle: create from user, find by user, assign positions, generate codes.
 *
 * @package Karnoweb\Hr\Services
 *
 * @see \Karnoweb\Hr\Models\Employee
 * @see \Karnoweb\Hr\Models\EmployeePosition
 */
class EmployeeService
{
    /**
     * Create an employee record linked to the given user (employable) model.
     *
     * @param  Model  $user  User model (must match config hr.models.user). Keys: employable_type/employable_id are set automatically.
     * @param  array<string, mixed>  $data  Employee attributes (e.g. employee_code, branch_id, hire_date, status). hire_date parsed from string if needed; defaults to now(). status defaults to Active. employee_code auto-generated if hr.employee_code.auto_generate is true.
     * @return Employee Created employee model.
     *
     * @throws \InvalidArgumentException When $user is not the configured HR user model.
     */
    public function createForUser(Model $user, array $data = []): Employee
    {
        $userClass = config('hr.models.user');

        if (! $user instanceof $userClass) {
            throw new \InvalidArgumentException('Given model is not the configured HR user model.');
        }

        $data['employable_type'] = get_class($user);
        $data['employable_id'] = $user->getKey();

        if (empty($data['employee_code']) && config('hr.employee_code.auto_generate', true)) {
            $data['employee_code'] = $this->generateEmployeeCode($data['branch_id'] ?? null);
        }

        if (empty($data['status'])) {
            $data['status'] = EmployeeStatus::Active;
        }

        if (! empty($data['hire_date']) && ! $data['hire_date'] instanceof \DateTimeInterface) {
            $data['hire_date'] = \Carbon\Carbon::parse($data['hire_date']);
        } elseif (empty($data['hire_date'])) {
            $data['hire_date'] = now();
        }

        return Employee::create($data);
    }

    /**
     * Find the employee record associated with the given user (employable) model.
     *
     * @param  Model  $user  User model to resolve employable_type and employable_id from.
     * @return Employee|null The employee for this user, or null if none.
     */
    public function findByUser(Model $user): ?Employee
    {
        return Employee::where('employable_type', get_class($user))
            ->where('employable_id', $user->getKey())
            ->first();
    }

    /**
     * Assign a department/position to an employee (creates an EmployeePosition record).
     *
     * @param  Employee  $employee  Employee to assign the position to.
     * @param  int  $departmentId  Department id.
     * @param  int  $positionId  Position id.
     * @param  \DateTimeInterface|string|null  $effectiveDate  Effective date; parsed via Carbon if string; defaults to now().
     * @param  array{is_primary?: bool, hr_document_id?: int|null, metadata?: array|null}  $options  is_primary (default true), hr_document_id, metadata.
     * @return EmployeePosition Created employee position record.
     */
    public function assignPosition(
        Employee $employee,
        int $departmentId,
        int $positionId,
        $effectiveDate = null,
        array $options = []
    ): EmployeePosition {
        $effectiveDate = $effectiveDate ? \Carbon\Carbon::parse($effectiveDate) : now();
        $isPrimary = $options['is_primary'] ?? true;
        $hrDocumentId = $options['hr_document_id'] ?? null;

        return EmployeePosition::create([
            'employee_id' => $employee->id,
            'department_id' => $departmentId,
            'position_id' => $positionId,
            'is_primary' => $isPrimary,
            'effective_date' => $effectiveDate,
            'end_date' => null,
            'hr_document_id' => $hrDocumentId,
            'metadata' => $options['metadata'] ?? null,
        ]);
    }

    /**
     * Generate next employee code according to hr.employee_code config (format, sequence_length, per_branch, per_year).
     *
     * @param  int|null  $branchId  Optional branch id when sequence_per_branch is enabled.
     * @return string Generated code (e.g. 1403-0001 for jalali year or 2024-0001 for gregorian).
     */
    public function generateEmployeeCode(?int $branchId = null): string
    {
        $config = config('hr.employee_code', []);
        $format = $config['format'] ?? '{year}-{sequence}';
        $length = (int) ($config['sequence_length'] ?? 4);
        $perBranch = $config['sequence_per_branch'] ?? false;
        $perYear = $config['sequence_per_year'] ?? true;

        $year = $this->resolveYear();

        $query = Employee::query();

        if ($perYear) {
            $query->where('employee_code', 'like', $year . '-%');
        }

        if ($perBranch) {
            $query->where('branch_id', $branchId);
        }

        $lastCode = $query->orderByDesc('employee_code')->value('employee_code');
        $lastSeq = 0;
        if ($lastCode && $perYear && str_starts_with($lastCode, $year . '-')) {
            $parts = explode('-', $lastCode, 2);
            $lastSeq = (int) ($parts[1] ?? 0);
        } elseif ($lastCode && ! $perYear) {
            $lastSeq = (int) preg_replace('/\D/', '', $lastCode);
        }

        $sequence = str_pad((string) ($lastSeq + 1), $length, '0', STR_PAD_LEFT);

        return str_replace(
            ['{year}', '{sequence}', '{branch}'],
            [$year, $sequence, (string) ($branchId ?? '')],
            $format
        );
    }

    /**
     * Resolve current year for code generation (gregorian or jalali per hr.calendar.type).
     *
     * @return string 4-digit year.
     */
    private function resolveYear(): string
    {
        $calendarType = config('hr.calendar.type', 'gregorian');

        if ($calendarType === 'jalali' && class_exists(\Morilog\Jalali\Jalalian::class)) {
            return (string) \Morilog\Jalali\Jalalian::now()->getYear();
        }

        return now()->format('Y');
    }
}
