<?php

namespace Karnoweb\Hr\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Karnoweb\Hr\Calculators\InsuranceCalculator;
use Karnoweb\Hr\Calculators\TaxCalculator;
use Karnoweb\Hr\Enums\AttendanceStatus;
use Karnoweb\Hr\Enums\LeaveRequestStatus;
use Karnoweb\Hr\Enums\OvertimeType;
use Karnoweb\Hr\Enums\PayrollRecordStatus;
use Karnoweb\Hr\Models\AttendanceRecord;
use Karnoweb\Hr\Models\Employee;
use Karnoweb\Hr\Models\LeaveRequest;
use Karnoweb\Hr\Models\MissionRequest;
use Karnoweb\Hr\Models\PayrollPeriod;
use Karnoweb\Hr\Support\PayrollBatchContext;
use Karnoweb\Hr\Support\PeriodRangeAllocator;
use Karnoweb\Hr\Support\SalaryPeriodAggregator;

/**
 * Per-employee payroll aggregation (HR-090–HR-096).
 *
 * Overtime hourly rate (HR-092): base_salary ÷ (period.working_days × daily_work_minutes).
 * daily_work_minutes defaults to 480 (config hr.payroll.daily_work_minutes).
 */
class PayrollCalculator
{
    public function __construct(
        protected SalaryService $salaries,
        protected SalaryCalculator $salaryCalculator,
        protected OvertimeService $overtime,
        protected LoanService $loans,
        protected InsuranceCalculator $insurance,
        protected TaxCalculator $tax,
        protected PeriodRangeAllocator $allocator,
        protected SalaryPeriodAggregator $salaryPeriods,
    ) {}

    /**
     * @param  Collection<int, Employee>  $employees
     */
    public function preloadBatch(Collection $employees, PayrollPeriod $period): PayrollBatchContext
    {
        return PayrollBatchContext::forPeriod($period, $employees);
    }

    /**
     * @return array<string, mixed>
     */
    public function calculateEmployee(
        Employee $employee,
        PayrollPeriod $period,
        ?PayrollBatchContext $batch = null,
    ): array {
        $periodStart = Carbon::parse($period->start_date)->startOfDay();
        $periodEnd = Carbon::parse($period->end_date)->endOfDay();
        $asOfDate = Carbon::parse($period->end_date);

        $attendance = $this->aggregateAttendance($employee, $period, $periodStart, $periodEnd, $batch);
        $leave = $this->aggregateLeave($employee, $periodStart, $periodEnd, $batch);
        $mission = $this->aggregateMissionDays($employee, $periodStart, $periodEnd, $batch);
        $overtime = $this->aggregateOvertime($employee, $period, $batch);
        $salary = $this->aggregateSalary($employee, $periodStart, $periodEnd, $batch);

        $baseSalary = $salary['base_salary'];
        $hourlyRate = $this->hourlyRate($baseSalary, (int) $period->working_days);
        $overtimeEarnings = $this->overtimeEarnings($overtime['minutes'], $hourlyRate);

        $earnings = array_merge(
            $salary['earnings'],
            $overtimeEarnings['items']
        );

        $salaryDeductions = $salary['deductions'];
        $grossEarnings = round($salary['totals']['earnings'] + $overtimeEarnings['total'], 2);
        $totalSalaryDeductions = round((float) $salary['totals']['deductions'], 2);
        $grossSalary = round($baseSalary + $grossEarnings - $totalSalaryDeductions, 2);

        $insurableBase = round(
            $baseSalary + (float) $salary['totals']['insurable_amount'] + $overtimeEarnings['total'],
            2
        );
        $taxableBase = round(
            $baseSalary + (float) $salary['totals']['taxable_amount'] + $overtimeEarnings['total'],
            2
        );

        $insurance = $this->insurance->calculate(
            $insurableBase,
            $asOfDate,
            (bool) $employee->insurance_exempt,
        );
        $priorTax = $batch !== null
            ? $batch->priorTaxFor((int) $employee->id)
            : ['taxable' => 0.0, 'tax' => 0.0, 'months' => 0];
        $taxResult = $this->tax->calculateMonthly(
            $taxableBase,
            $asOfDate,
            (int) ($employee->dependents_count ?? 0),
            (float) ($employee->additional_tax_exemption ?? 0),
            (bool) $employee->tax_exempt,
            [
                'taxable' => (float) $priorTax['taxable'],
                'tax' => (float) $priorTax['tax'],
                'months' => max(0, (int) $period->month - 1),
            ],
        );

        $loanPayments = $batch !== null
            ? $batch->loanPaymentsFor($employee->id)
            : $this->loans->deductionsForPeriod($employee, $period);
        $loanDeduction = round((float) $loanPayments->sum('amount'), 2);

        $netSalary = round(
            $grossSalary - $insurance['insurance_employee'] - $taxResult['tax'] - $loanDeduction,
            2
        );

        return [
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'working_days' => (int) $period->working_days,
            'present_days' => $attendance['present_days'],
            'absent_days' => $attendance['absent_days'],
            'leave_days_paid' => $leave['paid'],
            'leave_days_unpaid' => $leave['unpaid'],
            'mission_days' => $mission['days'],
            'late_minutes' => $attendance['late_minutes'],
            'early_leave_minutes' => $attendance['early_leave_minutes'],
            'overtime_minutes' => $overtime['minutes']['regular'],
            'overtime_night_minutes' => $overtime['minutes']['night'],
            'overtime_holiday_minutes' => $overtime['minutes']['holiday'],
            'earnings' => $earnings,
            'deductions' => $salaryDeductions,
            'base_salary' => $baseSalary,
            'gross_earnings' => $grossEarnings,
            'total_deductions' => $totalSalaryDeductions,
            'gross_salary' => $grossSalary,
            'insurance_employee' => $insurance['insurance_employee'],
            'insurance_employer' => $insurance['insurance_employer'],
            'insurance_unemployment' => $insurance['insurance_unemployment'],
            'taxable_income' => $taxResult['taxable_income'],
            'tax' => $taxResult['tax'],
            'loan_deduction' => $loanDeduction,
            'net_salary' => $netSalary,
            'payable' => $netSalary,
            'status' => PayrollRecordStatus::Calculated,
            'calculation_log' => [
                'hourly_rate' => $hourlyRate,
                'insurance' => $insurance,
                'tax' => $taxResult,
                'loan_payments' => $loanPayments->map(fn ($payment) => [
                    'id' => $payment->id,
                    'amount' => (float) $payment->amount,
                    'installment_number' => $payment->installment_number,
                ])->values()->all(),
                'salary_segments' => $salary['segments'],
                'leave_allocation' => $leave['allocations'],
                'mission_allocation' => $mission['allocations'],
                'policy' => [
                    'salary_resolution' => $salary['policy']['resolution'],
                    'salary_proration' => $salary['policy']['proration'],
                    'tax_method' => $taxResult['method'] ?? config('hr.tax.method'),
                    'daily_work_minutes' => (int) config('hr.payroll.daily_work_minutes', 480),
                ],
            ],
        ];
    }

    /**
     * @return array{present_days: int, absent_days: int, late_minutes: int, early_leave_minutes: int}
     */
    protected function aggregateAttendance(
        Employee $employee,
        PayrollPeriod $period,
        Carbon $periodStart,
        Carbon $periodEnd,
        ?PayrollBatchContext $batch = null,
    ): array {
        $records = $batch !== null
            ? $batch->attendanceFor($employee->id)
            : AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->whereDate('date', '>=', $periodStart->toDateString())
                ->whereDate('date', '<=', $periodEnd->toDateString())
                ->get();

        $presentDays = 0;
        $absentDays = 0;
        $lateMinutes = 0;
        $earlyLeaveMinutes = 0;

        foreach ($records as $record) {
            $lateMinutes += (int) ($record->late_minutes ?? 0);
            $earlyLeaveMinutes += (int) ($record->early_leave_minutes ?? 0);

            $status = $record->status instanceof AttendanceStatus
                ? $record->status
                : AttendanceStatus::tryFrom((string) $record->status);

            if ($status === AttendanceStatus::Present) {
                $presentDays++;
            } elseif ($status === AttendanceStatus::Absent) {
                $absentDays++;
            }
        }

        return [
            'present_days' => $presentDays,
            'absent_days' => $absentDays,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
        ];
    }

    /**
     * @return array{paid: float, unpaid: float, allocations: list<array<string, mixed>>}
     */
    protected function aggregateLeave(
        Employee $employee,
        Carbon $periodStart,
        Carbon $periodEnd,
        ?PayrollBatchContext $batch = null,
    ): array {
        $requests = $batch !== null
            ? $batch->leaveFor($employee->id)
            : LeaveRequest::query()
                ->where('employee_id', $employee->id)
                ->where('status', LeaveRequestStatus::Approved)
                ->whereDate('start_date', '<=', $periodEnd->toDateString())
                ->whereDate('end_date', '>=', $periodStart->toDateString())
                ->get();

        $paid = 0.0;
        $unpaid = 0.0;
        $allocations = [];

        foreach ($requests as $request) {
            $days = $this->allocator->allocateDaysInWindow(
                Carbon::parse($request->start_date),
                Carbon::parse($request->end_date),
                $periodStart,
                $periodEnd,
                (float) $request->days,
                $employee->branch_id,
            );
            $isPaid = (bool) config("hr.leave.types.{$request->type}.paid", true);

            if ($isPaid) {
                $paid += $days;
            } else {
                $unpaid += $days;
            }

            $allocations[] = [
                'leave_request_id' => $request->id,
                'type' => $request->type,
                'allocated_days' => $days,
                'paid' => $isPaid,
            ];
        }

        return [
            'paid' => round($paid, 2),
            'unpaid' => round($unpaid, 2),
            'allocations' => $allocations,
        ];
    }

    /**
     * @return array{days: float, allocations: list<array<string, mixed>>}
     */
    protected function aggregateMissionDays(
        Employee $employee,
        Carbon $periodStart,
        Carbon $periodEnd,
        ?PayrollBatchContext $batch = null,
    ): array {
        $missions = $batch !== null
            ? $batch->missionsFor($employee->id)
            : MissionRequest::query()
                ->where('employee_id', $employee->id)
                ->where('status', LeaveRequestStatus::Approved)
                ->whereDate('start_date', '<=', $periodEnd->toDateString())
                ->whereDate('end_date', '>=', $periodStart->toDateString())
                ->get();

        $total = 0.0;
        $allocations = [];

        foreach ($missions as $mission) {
            $days = $this->allocator->allocateDaysInWindow(
                Carbon::parse($mission->start_date),
                Carbon::parse($mission->end_date),
                $periodStart,
                $periodEnd,
                (float) $mission->days,
                $employee->branch_id,
            );
            $total += $days;
            $allocations[] = [
                'mission_request_id' => $mission->id,
                'allocated_days' => $days,
            ];
        }

        return [
            'days' => round($total, 2),
            'allocations' => $allocations,
        ];
    }

    /**
     * @return array{minutes: array{regular: int, night: int, holiday: int}}
     */
    protected function aggregateOvertime(
        Employee $employee,
        PayrollPeriod $period,
        ?PayrollBatchContext $batch = null,
    ): array {
        $totals = $batch !== null
            ? $batch->overtimeFor($employee->id)
            : $this->overtime->approvedMinutesForPeriod($employee, $period);

        return [
            'minutes' => [
                'regular' => (int) ($totals['regular'] ?? 0),
                'night' => (int) ($totals['night'] ?? 0),
                'holiday' => (int) ($totals['holiday'] ?? 0),
            ],
        ];
    }

    /**
     * @return array{
     *     base_salary: float,
     *     earnings: list<array<string, mixed>>,
     *     deductions: list<array<string, mixed>>,
     *     totals: array<string, float>,
     *     segments: list<array<string, mixed>>,
     *     policy: array{resolution: string, proration: string}
     * }
     */
    protected function aggregateSalary(
        Employee $employee,
        Carbon $periodStart,
        Carbon $periodEnd,
        ?PayrollBatchContext $batch = null,
    ): array {
        $salaries = $batch !== null
            ? $batch->salariesFor($employee->id)
            : $this->salaries->salariesForPeriod($employee, $periodStart, $periodEnd);

        if ($salaries->isEmpty() && config('hr.payroll.salary_resolution') === 'current') {
            $current = $this->salaries->currentSalary($employee);
            $salaries = $current !== null ? new Collection([$current]) : $salaries;
        }

        return $this->salaryPeriods->aggregate(
            $employee,
            $periodStart,
            $periodEnd,
            $salaries,
            $employee->branch_id,
        );
    }

    protected function hourlyRate(float $baseSalary, int $workingDays): float
    {
        $dailyMinutes = (int) config('hr.payroll.daily_work_minutes', 480);
        $denominator = max(1, $workingDays * $dailyMinutes);

        return round($baseSalary / $denominator, 6);
    }

    /**
     * @param  array{regular: int, night: int, holiday: int}  $minutes
     * @return array{total: float, items: list<array<string, mixed>>}
     */
    protected function overtimeEarnings(array $minutes, float $hourlyRate): array
    {
        $items = [];
        $total = 0.0;

        foreach (OvertimeType::cases() as $type) {
            $count = (int) ($minutes[$type->value] ?? 0);

            if ($count <= 0) {
                continue;
            }

            $amount = round(($count / 60) * $hourlyRate * $type->rate(), 2);
            $total += $amount;

            $items[] = [
                'code' => 'overtime_'.$type->value,
                'name' => 'Overtime '.$type->value,
                'amount' => $amount,
                'minutes' => $count,
                'rate' => $type->rate(),
            ];
        }

        return [
            'total' => round($total, 2),
            'items' => $items,
        ];
    }
}
