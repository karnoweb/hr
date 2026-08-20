<?php

namespace Karnoweb\Hr\Tests\Performance;

use Illuminate\Support\Facades\DB;
use Karnoweb\Hr\Facades\Hr;
use Karnoweb\Hr\Models\PayrollPeriod;
use Karnoweb\Hr\Tests\TestCase;

/**
 * Payroll period calculation should batch domain queries (HR-155).
 */
class PayrollBatchQueryTest extends TestCase
{
    public function test_period_calculate_query_count_does_not_scale_linearly_with_employees(): void
    {
        $smallCount = 5;
        $largeCount = 15;

        $smallQueries = $this->queryCountForEmployeeBatch($smallCount);
        $largeQueries = $this->queryCountForEmployeeBatch($largeCount);

        $employeeRatio = $largeCount / $smallCount;
        $queryRatio = $largeQueries / max(1, $smallQueries);

        $this->assertLessThan(
            $employeeRatio,
            $queryRatio,
            sprintf(
                'Query count scaled %.2fx for %.1fx employees (small=%d queries, large=%d queries). Expected sub-linear scaling.',
                $queryRatio,
                $employeeRatio,
                $smallQueries,
                $largeQueries,
            ),
        );
    }

    protected function queryCountForEmployeeBatch(int $employeeCount): int
    {
        for ($i = 0; $i < $employeeCount; $i++) {
            $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
            Hr::salaries()->assign($employee, [
                'base_salary' => 40_000_000 + ($i * 100_000),
                'effective_date' => '2026-01-01',
            ]);
        }

        $period = Hr::payroll()->openPeriod(1, 2026, 8 + $employeeCount);

        DB::flushQueryLog();
        DB::enableQueryLog();

        Hr::payroll()->calculate(PayrollPeriod::query()->findOrFail($period->id));

        return count(DB::getQueryLog());
    }
}
