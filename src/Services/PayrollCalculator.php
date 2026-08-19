<?php

namespace Karnoweb\Hr\Services;

use Carbon\Carbon;
use InvalidArgumentException;
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
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function calculateEmployee(Employee $employee, PayrollPeriod $period): array
    {
        $periodStart = Carbon::parse($period->start_date)->startOfDay();
        $periodEnd = Carbon::parse($period->end_date)->endOfDay();
        $asOfDate = Carbon::parse($period->end_date);

        $attendance = $this->aggregateAttendance($employee, $period, $periodStart, $periodEnd);
        $leave = $this->aggregateLeave($employee, $periodStart, $periodEnd);
        $missionDays = $this->aggregateMissionDays($employee, $periodStart, $periodEnd);
        $overtime = $this->aggregateOvertime($employee, $period);
        $salary = $this->aggregateSalary($employee);

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
        $taxResult = $this->tax->calculateMonthly(
            $taxableBase,
            $asOfDate,
            (int) ($employee->dependents_count ?? 0),
            (float) ($employee->additional_tax_exemption ?? 0),
            (bool) $employee->tax_exempt,
        );

        $loanPayments = $this->loans->deductionsForPeriod($employee, $period);
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
            'mission_days' => $missionDays,
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
        Carbon $periodEnd
    ): array {
        $records = AttendanceRecord::query()
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
     * @return array{paid: float, unpaid: float}
     */
    protected function aggregateLeave(Employee $employee, Carbon $periodStart, Carbon $periodEnd): array
    {
        $requests = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequestStatus::Approved)
            ->whereDate('start_date', '<=', $periodEnd->toDateString())
            ->whereDate('end_date', '>=', $periodStart->toDateString())
            ->get();

        $paid = 0.0;
        $unpaid = 0.0;

        foreach ($requests as $request) {
            $days = (float) $request->days;
            $isPaid = (bool) config("hr.leave.types.{$request->type}.paid", true);

            if ($isPaid) {
                $paid += $days;
            } else {
                $unpaid += $days;
            }
        }

        return [
            'paid' => round($paid, 2),
            'unpaid' => round($unpaid, 2),
        ];
    }

    protected function aggregateMissionDays(Employee $employee, Carbon $periodStart, Carbon $periodEnd): float
    {
        $days = MissionRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequestStatus::Approved)
            ->whereDate('start_date', '<=', $periodEnd->toDateString())
            ->whereDate('end_date', '>=', $periodStart->toDateString())
            ->sum('days');

        return round((float) $days, 2);
    }

    /**
     * @return array{minutes: array{regular: int, night: int, holiday: int}}
     */
    protected function aggregateOvertime(Employee $employee, PayrollPeriod $period): array
    {
        $totals = $this->overtime->approvedMinutesForPeriod($employee, $period);

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
     *     totals: array<string, float>
     * }
     */
    protected function aggregateSalary(Employee $employee): array
    {
        $current = $this->salaries->currentSalary($employee);

        if ($current === null) {
            throw new InvalidArgumentException("Employee {$employee->id} has no current salary for payroll.");
        }

        $calculated = $this->salaryCalculator->calculate($current);

        $earnings = [];
        $deductions = [];

        foreach ($calculated['items'] as $item) {
            $row = [
                'code' => $item['code'],
                'name' => $item['name'],
                'amount' => $item['amount'],
            ];

            if ($item['type'] === 'earning') {
                $earnings[] = $row;
            } else {
                $deductions[] = $row;
            }
        }

        return [
            'base_salary' => (float) $calculated['base_salary'],
            'earnings' => $earnings,
            'deductions' => $deductions,
            'totals' => $calculated['totals'],
        ];
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
