<?php

namespace Karnoweb\Hr\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Karnoweb\Hr\Enums\LeaveRequestStatus;
use Karnoweb\Hr\Enums\LoanPaymentStatus;
use Karnoweb\Hr\Enums\LoanStatus;
use Karnoweb\Hr\Enums\OvertimeStatus;
use Karnoweb\Hr\Enums\OvertimeType;
use Karnoweb\Hr\Models\AttendanceRecord;
use Karnoweb\Hr\Models\Employee;
use Karnoweb\Hr\Models\EmployeeSalary;
use Karnoweb\Hr\Models\LeaveRequest;
use Karnoweb\Hr\Models\Loan;
use Karnoweb\Hr\Models\LoanPayment;
use Karnoweb\Hr\Models\MissionRequest;
use Karnoweb\Hr\Models\OvertimeRecord;
use Karnoweb\Hr\Models\PayrollPeriod;

/**
 * Preloaded payroll inputs for an entire period (HR-155).
 *
 * @internal Built by {@see PayrollCalculator::preloadBatch()}.
 */
final class PayrollBatchContext
{
    /**
     * @param  Collection<int, EloquentCollection<int, AttendanceRecord>>  $attendanceByEmployee
     * @param  Collection<int, EloquentCollection<int, LeaveRequest>>  $leaveByEmployee
     * @param  array<int, float>  $missionDaysByEmployee
     * @param  array<int, array<string, int>>  $overtimeByEmployee
     * @param  Collection<int, EmployeeSalary>  $salariesByEmployee
     * @param  Collection<int, EloquentCollection<int, LoanPayment>>  $loanPaymentsByEmployee
     */
    public function __construct(
        private Collection $attendanceByEmployee,
        private Collection $leaveByEmployee,
        private array $missionDaysByEmployee,
        private array $overtimeByEmployee,
        private Collection $salariesByEmployee,
        private Collection $loanPaymentsByEmployee,
    ) {}

    /**
     * @param  EloquentCollection<int, Employee>  $employees
     */
    public static function forPeriod(PayrollPeriod $period, EloquentCollection $employees): self
    {
        $employeeIds = $employees->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($employeeIds === []) {
            return new self(
                collect(),
                collect(),
                [],
                [],
                collect(),
                collect(),
            );
        }

        $periodStart = Carbon::parse($period->start_date)->startOfDay();
        $periodEnd = Carbon::parse($period->end_date)->endOfDay();
        $startDate = $periodStart->toDateString();
        $endDate = $periodEnd->toDateString();

        $attendanceByEmployee = AttendanceRecord::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->get()
            ->groupBy('employee_id');

        $leaveByEmployee = LeaveRequest::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', LeaveRequestStatus::Approved)
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->get()
            ->groupBy('employee_id');

        $missionDaysByEmployee = MissionRequest::query()
            ->selectRaw('employee_id, SUM(days) as total_days')
            ->whereIn('employee_id', $employeeIds)
            ->where('status', LeaveRequestStatus::Approved)
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->groupBy('employee_id')
            ->pluck('total_days', 'employee_id')
            ->map(fn ($days) => round((float) $days, 2))
            ->all();

        $overtimeByEmployee = self::loadOvertimeTotals($employeeIds, $startDate, $endDate);

        $salariesByEmployee = EmployeeSalary::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('is_current', true)
            ->with(['items.salaryItem', 'salaryStructure.items.salaryItem'])
            ->get()
            ->keyBy('employee_id');

        $loanPaymentsByEmployee = LoanPayment::query()
            ->where('status', LoanPaymentStatus::Pending)
            ->whereDate('due_date', '>=', $startDate)
            ->whereDate('due_date', '<=', $endDate)
            ->whereHas('loan', function ($query) use ($employeeIds) {
                $query->whereIn('employee_id', $employeeIds)
                    ->where('status', LoanStatus::Active);
            })
            ->with('loan:id,employee_id')
            ->orderBy('due_date')
            ->get()
            ->groupBy(function (LoanPayment $payment): int {
                $loan = $payment->loan;

                return $loan instanceof Loan ? (int) $loan->employee_id : 0;
            });

        return new self(
            $attendanceByEmployee,
            $leaveByEmployee,
            $missionDaysByEmployee,
            $overtimeByEmployee,
            $salariesByEmployee,
            $loanPaymentsByEmployee,
        );
    }

    /**
     * @return EloquentCollection<int, AttendanceRecord>
     */
    public function attendanceFor(int $employeeId): EloquentCollection
    {
        /** @var EloquentCollection<int, AttendanceRecord> $records */
        $records = $this->attendanceByEmployee->get($employeeId, new EloquentCollection);

        return $records;
    }

    /**
     * @return EloquentCollection<int, LeaveRequest>
     */
    public function leaveFor(int $employeeId): EloquentCollection
    {
        /** @var EloquentCollection<int, LeaveRequest> $requests */
        $requests = $this->leaveByEmployee->get($employeeId, new EloquentCollection);

        return $requests;
    }

    public function missionDaysFor(int $employeeId): float
    {
        return $this->missionDaysByEmployee[$employeeId] ?? 0.0;
    }

    /**
     * @return array<string, int>
     */
    public function overtimeFor(int $employeeId): array
    {
        return $this->overtimeByEmployee[$employeeId] ?? self::emptyOvertimeTotals();
    }

    public function salaryFor(int $employeeId): ?EmployeeSalary
    {
        return $this->salariesByEmployee->get($employeeId);
    }

    /**
     * @return EloquentCollection<int, LoanPayment>
     */
    public function loanPaymentsFor(int $employeeId): EloquentCollection
    {
        /** @var EloquentCollection<int, LoanPayment> $payments */
        $payments = $this->loanPaymentsByEmployee->get($employeeId, new EloquentCollection);

        return $payments;
    }

    /**
     * @param  list<int>  $employeeIds
     * @return array<int, array<string, int>>
     */
    protected static function loadOvertimeTotals(array $employeeIds, string $startDate, string $endDate): array
    {
        $totals = [];

        foreach ($employeeIds as $employeeId) {
            $totals[$employeeId] = self::emptyOvertimeTotals();
        }

        $rows = OvertimeRecord::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', OvertimeStatus::Approved)
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->get(['employee_id', 'type', 'approved_minutes', 'calculated_minutes']);

        foreach ($rows as $row) {
            $employeeId = (int) $row->employee_id;
            $type = $row->type instanceof OvertimeType ? $row->type->value : (string) $row->type;
            $totals[$employeeId][$type] = ($totals[$employeeId][$type] ?? 0)
                + (int) ($row->approved_minutes ?? $row->calculated_minutes);
        }

        return $totals;
    }

    /**
     * @return array<string, int>
     */
    protected static function emptyOvertimeTotals(): array
    {
        $totals = [];

        foreach (OvertimeType::cases() as $type) {
            $totals[$type->value] = 0;
        }

        return $totals;
    }
}
