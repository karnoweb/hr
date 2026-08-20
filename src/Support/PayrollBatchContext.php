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
use Karnoweb\Hr\Models\PayrollRecord;
use Karnoweb\Hr\Services\PayrollCalculator;

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
     * @param  Collection<int, EloquentCollection<int, MissionRequest>>  $missionsByEmployee
     * @param  array<int, array<string, int>>  $overtimeByEmployee
     * @param  Collection<int, EloquentCollection<int, EmployeeSalary>>  $salariesByEmployee
     * @param  Collection<int, EloquentCollection<int, LoanPayment>>  $loanPaymentsByEmployee
     * @param  array<int, array{taxable: float, tax: float, months: int}>  $priorTaxByEmployee
     */
    public function __construct(
        private Collection $attendanceByEmployee,
        private Collection $leaveByEmployee,
        private Collection $missionsByEmployee,
        private array $overtimeByEmployee,
        private Collection $salariesByEmployee,
        private Collection $loanPaymentsByEmployee,
        private array $priorTaxByEmployee = [],
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
                collect(),
                [],
                collect(),
                collect(),
                [],
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

        $missionsByEmployee = MissionRequest::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', LeaveRequestStatus::Approved)
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->get()
            ->groupBy('employee_id');

        $overtimeByEmployee = self::loadOvertimeTotals($employeeIds, $startDate, $endDate);

        $salariesByEmployee = EmployeeSalary::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('effective_date', '<=', $endDate)
            ->where(function ($query) use ($startDate) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $startDate);
            })
            ->with(['items.salaryItem', 'salaryStructure.items.salaryItem'])
            ->orderBy('effective_date')
            ->get()
            ->groupBy('employee_id');

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
            $missionsByEmployee,
            $overtimeByEmployee,
            $salariesByEmployee,
            $loanPaymentsByEmployee,
            self::loadPriorTax($employeeIds, $period),
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

    /**
     * @return EloquentCollection<int, MissionRequest>
     */
    public function missionsFor(int $employeeId): EloquentCollection
    {
        /** @var EloquentCollection<int, MissionRequest> $missions */
        $missions = $this->missionsByEmployee->get($employeeId, new EloquentCollection);

        return $missions;
    }

    /**
     * @return array<string, int>
     */
    public function overtimeFor(int $employeeId): array
    {
        return $this->overtimeByEmployee[$employeeId] ?? self::emptyOvertimeTotals();
    }

    /**
     * @return EloquentCollection<int, EmployeeSalary>
     */
    public function salariesFor(int $employeeId): EloquentCollection
    {
        /** @var EloquentCollection<int, EmployeeSalary> $salaries */
        $salaries = $this->salariesByEmployee->get($employeeId, new EloquentCollection);

        return $salaries;
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
     * @return array{taxable: float, tax: float, months: int}
     */
    public function priorTaxFor(int $employeeId): array
    {
        return $this->priorTaxByEmployee[$employeeId] ?? ['taxable' => 0.0, 'tax' => 0.0, 'months' => 0];
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
     * @param  list<int>  $employeeIds
     * @return array<int, array{taxable: float, tax: float, months: int}>
     */
    protected static function loadPriorTax(array $employeeIds, PayrollPeriod $period): array
    {
        $totals = [];

        foreach ($employeeIds as $employeeId) {
            $totals[$employeeId] = ['taxable' => 0.0, 'tax' => 0.0, 'months' => 0];
        }

        $recordTable = (new PayrollRecord)->getTable();
        $periodTable = (new PayrollPeriod)->getTable();

        $rows = PayrollRecord::query()
            ->select([
                "{$recordTable}.employee_id",
                "{$recordTable}.taxable_income",
                "{$recordTable}.tax",
            ])
            ->join($periodTable, "{$periodTable}.id", '=', "{$recordTable}.payroll_period_id")
            ->whereIn("{$recordTable}.employee_id", $employeeIds)
            ->where("{$periodTable}.year", $period->year)
            ->where("{$periodTable}.month", '<', $period->month)
            ->get();

        foreach ($rows as $row) {
            $employeeId = (int) $row->employee_id;
            $totals[$employeeId]['taxable'] += (float) $row->taxable_income;
            $totals[$employeeId]['tax'] += (float) $row->tax;
            $totals[$employeeId]['months']++;
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
