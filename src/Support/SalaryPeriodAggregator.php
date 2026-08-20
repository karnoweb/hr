<?php

namespace Karnoweb\Hr\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Karnoweb\Hr\Models\Employee;
use Karnoweb\Hr\Models\EmployeeSalary;
use Karnoweb\Hr\Services\SalaryCalculator;

/**
 * Resolves the salary(s) effective during a payroll period and optionally prorates
 * base pay and items across mid-period changes.
 */
final class SalaryPeriodAggregator
{
    public function __construct(
        protected SalaryCalculator $calculator,
        protected WorkingDayCalculator $workingDays,
    ) {}

    /**
     * @param  iterable<int, EmployeeSalary>  $salaries
     * @return array{
     *     base_salary: float,
     *     earnings: list<array<string, mixed>>,
     *     deductions: list<array<string, mixed>>,
     *     totals: array<string, float>,
     *     segments: list<array<string, mixed>>,
     *     policy: array{resolution: string, proration: string}
     * }
     */
    public function aggregate(
        Employee $employee,
        Carbon $periodStart,
        Carbon $periodEnd,
        iterable $salaries,
        ?int $branchId = null,
    ): array {
        $periodStart = $periodStart->copy()->startOfDay();
        $periodEnd = $periodEnd->copy()->startOfDay();
        $proration = (string) config('hr.payroll.salary_proration', 'calendar_days');
        $resolution = (string) config('hr.payroll.salary_resolution', 'period_effective');

        $records = Collection::make($salaries)->values();

        if ($records->isEmpty()) {
            throw new InvalidArgumentException(
                "Employee {$employee->id} has no salary overlapping the payroll period."
            );
        }

        $segments = $proration === 'none'
            ? $this->singleSegment($records, $periodStart, $periodEnd)
            : $this->buildSegments($records, $periodStart, $periodEnd, $proration, $branchId);

        if ($segments === []) {
            throw new InvalidArgumentException(
                "Employee {$employee->id} has no salary overlapping the payroll period."
            );
        }

        $denominator = $this->denominator($periodStart, $periodEnd, $proration, $branchId);
        $baseSalary = 0.0;
        $mergedItems = [];
        $loggedSegments = [];

        foreach ($segments as $segment) {
            /** @var EmployeeSalary $salary */
            $salary = $segment['salary'];
            $weight = $proration === 'none' ? 1.0 : ($segment['days'] / $denominator);
            $calculated = $this->calculator->calculate($salary);
            $baseSalary += (float) $calculated['base_salary'] * $weight;

            foreach ($calculated['items'] as $item) {
                $code = (string) $item['code'];
                $mergedItems[$code] ??= [
                    'code' => $code,
                    'name' => $item['name'],
                    'type' => $item['type'],
                    'amount' => 0.0,
                    'is_taxable' => (bool) $item['is_taxable'],
                    'is_insurable' => (bool) $item['is_insurable'],
                ];
                $mergedItems[$code]['amount'] = round(
                    $mergedItems[$code]['amount'] + ((float) $item['amount'] * $weight),
                    2
                );
            }

            $loggedSegments[] = [
                'employee_salary_id' => $salary->id,
                'from' => $segment['from'],
                'to' => $segment['to'],
                'days' => $segment['days'],
                'weight' => round($weight, 6),
                'base_salary' => (float) $calculated['base_salary'],
            ];
        }

        $earnings = [];
        $deductions = [];
        $itemTotals = [
            'earnings' => 0.0,
            'deductions' => 0.0,
            'taxable_amount' => 0.0,
            'insurable_amount' => 0.0,
        ];

        foreach ($mergedItems as $item) {
            $amount = round((float) $item['amount'], 2);
            $row = [
                'code' => $item['code'],
                'name' => $item['name'],
                'amount' => $amount,
            ];

            if ($item['type'] === 'earning') {
                $earnings[] = $row;
                $itemTotals['earnings'] += $amount;

                if ($item['is_taxable']) {
                    $itemTotals['taxable_amount'] += $amount;
                }

                if ($item['is_insurable']) {
                    $itemTotals['insurable_amount'] += $amount;
                }
            } else {
                $deductions[] = $row;
                $itemTotals['deductions'] += $amount;
            }
        }

        $baseSalary = round($baseSalary, 2);

        return [
            'base_salary' => $baseSalary,
            'earnings' => $earnings,
            'deductions' => $deductions,
            'totals' => [
                'earnings' => round($itemTotals['earnings'], 2),
                'deductions' => round($itemTotals['deductions'], 2),
                'taxable_amount' => round($itemTotals['taxable_amount'], 2),
                'insurable_amount' => round($itemTotals['insurable_amount'], 2),
                'gross_earnings' => round($baseSalary + $itemTotals['earnings'], 2),
                'net_before_statutory' => round($baseSalary + $itemTotals['earnings'] - $itemTotals['deductions'], 2),
            ],
            'segments' => $loggedSegments,
            'policy' => [
                'resolution' => $resolution,
                'proration' => $proration,
            ],
        ];
    }

    /**
     * @param  Collection<int, EmployeeSalary>  $salaries
     * @return list<array{salary: EmployeeSalary, from: string, to: string, days: int}>
     */
    protected function singleSegment(Collection $salaries, Carbon $periodStart, Carbon $periodEnd): array
    {
        $chosen = $this->salaryOnDate($salaries, $periodEnd) ?? $salaries->sortByDesc('effective_date')->first();

        if ($chosen === null) {
            return [];
        }

        return [[
            'salary' => $chosen,
            'from' => $periodStart->toDateString(),
            'to' => $periodEnd->toDateString(),
            'days' => (int) $periodStart->diffInDays($periodEnd) + 1,
        ]];
    }

    /**
     * @param  Collection<int, EmployeeSalary>  $salaries
     * @return list<array{salary: EmployeeSalary, from: string, to: string, days: int}>
     */
    protected function buildSegments(
        Collection $salaries,
        Carbon $periodStart,
        Carbon $periodEnd,
        string $proration,
        ?int $branchId,
    ): array {
        $buckets = [];

        for ($cursor = $periodStart->copy(); $cursor->lte($periodEnd); $cursor->addDay()) {
            if ($proration === 'working_days' && ! $this->workingDays->isWorkingDay($cursor, $branchId)) {
                continue;
            }

            $salary = $this->salaryOnDate($salaries, $cursor);

            if ($salary === null) {
                continue;
            }

            $id = (int) $salary->id;

            if (! isset($buckets[$id])) {
                $buckets[$id] = [
                    'salary' => $salary,
                    'from' => $cursor->toDateString(),
                    'to' => $cursor->toDateString(),
                    'days' => 0,
                ];
            }

            $buckets[$id]['to'] = $cursor->toDateString();
            $buckets[$id]['days']++;
        }

        return array_values($buckets);
    }

    /**
     * @param  Collection<int, EmployeeSalary>  $salaries
     */
    protected function salaryOnDate(Collection $salaries, Carbon $date): ?EmployeeSalary
    {
        $day = $date->copy()->startOfDay();
        $chosen = null;

        foreach ($salaries as $salary) {
            $effective = $salary->effective_date?->copy()->startOfDay();
            $end = $salary->end_date?->copy()->startOfDay();

            if ($effective === null || $effective->gt($day)) {
                continue;
            }

            if ($end !== null && $end->lt($day)) {
                continue;
            }

            if ($chosen === null || $effective->gt($chosen->effective_date->copy()->startOfDay())) {
                $chosen = $salary;
            }
        }

        return $chosen;
    }

    protected function denominator(
        Carbon $periodStart,
        Carbon $periodEnd,
        string $proration,
        ?int $branchId,
    ): int {
        if ($proration === 'working_days') {
            return max(1, $this->workingDays->count($periodStart, $periodEnd, $branchId));
        }

        return max(1, (int) $periodStart->diffInDays($periodEnd) + 1);
    }
}
