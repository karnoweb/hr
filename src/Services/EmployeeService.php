<?php

namespace Karnoweb\Hr\Services;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon as IlluminateCarbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Karnoweb\Hr\Enums\ApprovalStatus;
use Karnoweb\Hr\Enums\ContractStatus;
use Karnoweb\Hr\Enums\EmployeeStatus;
use Karnoweb\Hr\Enums\LeaveRequestStatus;
use Karnoweb\Hr\Enums\LoanStatus;
use Karnoweb\Hr\Exceptions\DuplicateActiveRecordException;
use Karnoweb\Hr\Exceptions\EmployeeAlreadyExistsException;
use Karnoweb\Hr\Exceptions\InvalidEmployeeLifecycleException;
use Karnoweb\Hr\Models\Contract;
use Karnoweb\Hr\Models\Department;
use Karnoweb\Hr\Models\DocumentApproval;
use Karnoweb\Hr\Models\Employee;
use Karnoweb\Hr\Models\EmployeePosition;
use Karnoweb\Hr\Models\EmployeeSalary;
use Karnoweb\Hr\Models\HrDocument;
use Karnoweb\Hr\Models\LeaveRequest;
use Karnoweb\Hr\Models\Loan;
use Karnoweb\Hr\Models\MissionRequest;
use Karnoweb\Hr\Models\Position;
use Karnoweb\Hr\Support\HrDocumentReference;
use Karnoweb\Hr\Support\QueryExceptionClassifier;
use Karnoweb\Hr\Support\SequenceGenerator;
use Morilog\Jalali\Jalalian;

/**
 * Employee lifecycle service: create, find, assign positions, suspend, terminate, reactivate.
 *
 * Authorization and branch-scoping are the integrating application's responsibility.
 * This package does not enforce who may call these methods or which branch's employees
 * a caller may see — see docs/USAGE.md "Security model".
 *
 * @see Employee
 * @see EmployeePosition
 */
class EmployeeService
{
    public function __construct(
        protected SequenceGenerator $sequences,
    ) {}

    /**
     * Create an employee record linked to the given user (employable) model.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException When $user is not the configured HR user model.
     * @throws EmployeeAlreadyExistsException When an employee already exists for this user.
     */
    public function createForUser(Model $user, array $data = []): Employee
    {
        $userClass = config('hr.models.user');

        if (! $user instanceof $userClass) {
            throw new InvalidArgumentException('Given model is not the configured HR user model.');
        }

        $existing = Employee::withTrashed()
            ->where('employable_type', $user::class)
            ->where('employable_id', $user->getKey())
            ->first();

        if ($existing !== null) {
            throw new EmployeeAlreadyExistsException(
                $existing->trashed()
                    ? 'A soft-deleted employee already exists for this user. Reactivate or restore it instead of creating a new one.'
                    : 'An employee already exists for this user.'
            );
        }

        $data['employable_type'] = $user::class;
        $data['employable_id'] = $user->getKey();

        if (empty($data['employee_code']) && config('hr.employee_code.auto_generate', true)) {
            $data['employee_code'] = $this->generateEmployeeCode(
                isset($data['branch_id']) ? (int) $data['branch_id'] : null
            );
        }

        if (empty($data['status'])) {
            $data['status'] = EmployeeStatus::Active;
        }

        if (! empty($data['hire_date']) && ! $data['hire_date'] instanceof DateTimeInterface) {
            $data['hire_date'] = Carbon::parse($data['hire_date']);
        } elseif (empty($data['hire_date'])) {
            $data['hire_date'] = now();
        }

        try {
            return Employee::create($data);
        } catch (QueryException $e) {
            if (QueryExceptionClassifier::isUniqueViolation($e)) {
                throw new EmployeeAlreadyExistsException(
                    'An employee with the same identity already exists (employable or national_id).',
                    previous: $e
                );
            }

            throw $e;
        }
    }

    /**
     * Find the employee record associated with the given user (employable) model.
     */
    public function findByUser(Model $user): ?Employee
    {
        return Employee::query()
            ->where('employable_type', $user::class)
            ->where('employable_id', $user->getKey())
            ->first();
    }

    /**
     * Assign a department/position to an employee, closing any current primary assignment first.
     *
     * @param  DateTimeInterface|string|null  $effectiveDate
     * @param  array{is_primary?: bool, hr_document_id?: int|null, metadata?: array|null, allow_cross_branch?: bool}  $options
     *
     * @throws InvalidArgumentException When branch mismatch or invalid hr_document_id.
     * @throws DuplicateActiveRecordException When the DB current_key invariant is violated.
     */
    public function assignPosition(
        Employee $employee,
        int $departmentId,
        int $positionId,
        $effectiveDate = null,
        array $options = []
    ): EmployeePosition {
        $effectiveDate = $effectiveDate ? Carbon::parse($effectiveDate)->startOfDay() : now()->startOfDay();
        $isPrimary = $options['is_primary'] ?? true;
        $hrDocumentId = $options['hr_document_id'] ?? null;
        $allowCrossBranch = (bool) ($options['allow_cross_branch'] ?? false);

        HrDocumentReference::assertValid($hrDocumentId !== null ? (int) $hrDocumentId : null);

        return DB::transaction(function () use (
            $employee,
            $departmentId,
            $positionId,
            $effectiveDate,
            $isPrimary,
            $hrDocumentId,
            $allowCrossBranch,
            $options
        ) {
            $employee = Employee::query()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();

            $department = Department::query()->whereKey($departmentId)->firstOrFail();
            $position = Position::query()->whereKey($positionId)->firstOrFail();

            if (! $allowCrossBranch && $employee->branch_id !== null) {
                if ($department->branch_id !== null && (int) $department->branch_id !== (int) $employee->branch_id) {
                    throw new InvalidArgumentException(
                        'Department does not belong to the employee branch. Pass allow_cross_branch=true to override.'
                    );
                }

                if ($position->branch_id !== null && (int) $position->branch_id !== (int) $employee->branch_id) {
                    throw new InvalidArgumentException(
                        'Position does not belong to the employee branch. Pass allow_cross_branch=true to override.'
                    );
                }
            }

            if ($isPrimary) {
                EmployeePosition::query()
                    ->where('employee_id', $employee->id)
                    ->where('is_primary', true)
                    ->whereNull('end_date')
                    ->lockForUpdate()
                    ->get()
                    ->each(function (EmployeePosition $existing) use ($effectiveDate): void {
                        $existing->update([
                            'end_date' => $effectiveDate->toDateString(),
                            'current_key' => null,
                        ]);
                    });
            }

            try {
                return EmployeePosition::create([
                    'employee_id' => $employee->id,
                    'department_id' => $departmentId,
                    'position_id' => $positionId,
                    'is_primary' => $isPrimary,
                    'effective_date' => $effectiveDate->toDateString(),
                    'end_date' => null,
                    'current_key' => $isPrimary ? $employee->id : null,
                    'hr_document_id' => $hrDocumentId,
                    'metadata' => $options['metadata'] ?? null,
                ]);
            } catch (QueryException $e) {
                if (QueryExceptionClassifier::isUniqueViolation($e)) {
                    throw new DuplicateActiveRecordException(
                        'Could not assign position: employee already has a current primary assignment.',
                        previous: $e
                    );
                }

                throw $e;
            }
        });
    }

    /**
     * Suspend an active employee without closing contracts, positions, or salary.
     *
     * Unlike terminate(), this is a temporary hold: related records stay open and
     * the employee can later return to Active via a direct lifecycle path once
     * unsuspend is needed (today: terminate is for permanent exit; reactivate is
     * for Terminated → Active rehire).
     *
     * @throws InvalidEmployeeLifecycleException
     */
    public function suspend(Employee $employee): Employee
    {
        return DB::transaction(function () use ($employee) {
            $employee = Employee::query()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();

            if ($employee->status !== EmployeeStatus::Active) {
                throw new InvalidEmployeeLifecycleException(
                    "Only active employees can be suspended (current status: {$employee->status->value})."
                );
            }

            $employee->applyLifecycleStatus(EmployeeStatus::Suspended);
            $employee->save();

            return $employee->refresh();
        });
    }

    /**
     * Terminate an employee and close related open records atomically.
     *
     * Side effects (all-or-nothing):
     * - status → Terminated, termination_date set
     * - active contracts → Terminated with end_date
     * - open positions → end_date set
     * - current salaries → is_current=false, end_date set
     * - pending leave/mission requests → Cancelled
     * - pending loans → Cancelled (active/disbursed loans are left alone)
     * - pending document approvals for this employee's documents, or assigned to
     *   their employable id → Skipped
     *
     *
     * @throws InvalidEmployeeLifecycleException
     */
    public function terminate(Employee $employee, DateTimeInterface|string|null $terminationDate = null): Employee
    {
        return DB::transaction(function () use ($employee, $terminationDate) {
            $employee = Employee::query()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();

            if ($employee->status === EmployeeStatus::Terminated) {
                throw new InvalidEmployeeLifecycleException('Employee is already terminated.');
            }

            $date = $terminationDate
                ? IlluminateCarbon::parse($terminationDate)->startOfDay()
                : IlluminateCarbon::now()->startOfDay();

            if ($employee->hire_date !== null && $date->lt($employee->hire_date->copy()->startOfDay())) {
                throw new InvalidEmployeeLifecycleException(
                    'termination_date must be on or after hire_date.'
                );
            }

            $employee->applyLifecycleStatus(EmployeeStatus::Terminated);
            $employee->termination_date = $date;
            $employee->save();

            Contract::query()
                ->where('employee_id', $employee->id)
                ->where('status', ContractStatus::Active)
                ->update([
                    'status' => ContractStatus::Terminated,
                    'end_date' => $date->toDateString(),
                    'active_key' => null,
                    'updated_at' => now(),
                ]);

            EmployeePosition::query()
                ->where('employee_id', $employee->id)
                ->whereNull('end_date')
                ->update([
                    'end_date' => $date->toDateString(),
                    'current_key' => null,
                    'updated_at' => now(),
                ]);

            EmployeeSalary::query()
                ->where('employee_id', $employee->id)
                ->where('is_current', true)
                ->update([
                    'is_current' => false,
                    'end_date' => $date->toDateString(),
                    'current_key' => null,
                    'updated_at' => now(),
                ]);

            LeaveRequest::query()
                ->where('employee_id', $employee->id)
                ->where('status', LeaveRequestStatus::Pending)
                ->update([
                    'status' => LeaveRequestStatus::Cancelled,
                    'updated_at' => now(),
                ]);

            MissionRequest::query()
                ->where('employee_id', $employee->id)
                ->where('status', LeaveRequestStatus::Pending)
                ->update([
                    'status' => LeaveRequestStatus::Cancelled,
                    'updated_at' => now(),
                ]);

            Loan::query()
                ->where('employee_id', $employee->id)
                ->where('status', LoanStatus::Pending)
                ->update([
                    'status' => LoanStatus::Cancelled,
                    'updated_at' => now(),
                ]);

            $documentIds = HrDocument::query()
                ->where('employee_id', $employee->id)
                ->pluck('id');

            DocumentApproval::query()
                ->where('status', ApprovalStatus::Pending)
                ->where(function ($query) use ($employee, $documentIds) {
                    $query->whereIn('hr_document_id', $documentIds)
                        ->orWhere('assigned_to', $employee->employable_id);
                })
                ->update([
                    'status' => ApprovalStatus::Skipped,
                    'acted_at' => now(),
                    'updated_at' => now(),
                ]);

            app(LeaveBalanceService::class)->handleTermination($employee);

            return $employee->refresh();
        });
    }

    /**
     * Reactivate (rehire) a terminated employee.
     *
     * Policy: the existing `employee_code` is **kept**. A rehire is the same person
     * returning; regenerating a code would break historical payroll/document links.
     * Callers that need a brand-new identity should create a new Employee instead
     * (blocked for the same employable by the unique index — use a different user).
     *
     * Clears termination_date and sets status to Active. Does not recreate
     * contract / position / salary — those remain separate domain operations.
     *
     * @throws InvalidEmployeeLifecycleException
     */
    public function reactivate(Employee $employee): Employee
    {
        return DB::transaction(function () use ($employee) {
            $employee = Employee::query()->whereKey($employee->getKey())->lockForUpdate()->firstOrFail();

            if ($employee->status !== EmployeeStatus::Terminated) {
                throw new InvalidEmployeeLifecycleException(
                    "Only terminated employees can be reactivated (current status: {$employee->status->value})."
                );
            }

            $employee->applyLifecycleStatus(EmployeeStatus::Active);
            $employee->termination_date = null;
            $employee->save();

            return $employee->refresh();
        });
    }

    /**
     * Generate next employee code via SequenceGenerator (atomic).
     *
     * When `sequence_per_branch` is true, `hr.employee_code.format` **must** contain
     * `{branch}` so codes from different branches cannot collide on the global
     * unique `employee_code` column.
     *
     * @throws InvalidArgumentException
     */
    public function generateEmployeeCode(?int $branchId = null): string
    {
        $config = config('hr.employee_code', []);
        $format = $config['format'] ?? '{year}-{sequence}';
        $length = (int) ($config['sequence_length'] ?? 4);
        $perBranch = (bool) ($config['sequence_per_branch'] ?? false);
        $perYear = (bool) ($config['sequence_per_year'] ?? true);

        if ($perBranch && ! str_contains($format, '{branch}')) {
            throw new InvalidArgumentException(
                'hr.employee_code.sequence_per_branch is enabled but format is missing {branch}. '
                .'Include {branch} in hr.employee_code.format (e.g. "{year}-{branch}-{sequence}") '
                .'to prevent cross-branch employee_code collisions.'
            );
        }

        $year = $this->resolveYear();
        $branchToken = $branchId === null ? '0' : (string) $branchId;

        $scopeParts = ['employee_code'];
        if ($perYear) {
            $scopeParts[] = 'year:'.$year;
        }
        if ($perBranch) {
            $scopeParts[] = 'branch:'.$branchToken;
        }

        $sequenceNumber = $this->sequences->nextValue(implode(':', $scopeParts));
        $sequence = str_pad((string) $sequenceNumber, $length, '0', STR_PAD_LEFT);

        return str_replace(
            ['{year}', '{sequence}', '{branch}'],
            [$year, $sequence, $branchToken],
            $format
        );
    }

    /**
     * Resolve current year for code generation (gregorian or jalali per hr.calendar.type).
     */
    private function resolveYear(): string
    {
        $calendarType = config('hr.calendar.type', 'gregorian');

        if ($calendarType === 'jalali' && class_exists(Jalalian::class)) {
            return (string) Jalalian::now()->getYear();
        }

        return now()->format('Y');
    }
}
