<?php

namespace Karnoweb\Hr\Tests\Unit;

use Illuminate\Support\Carbon;
use Karnoweb\Hr\Models\Holiday;
use Karnoweb\Hr\Support\WorkingDayCalculator;
use Karnoweb\Hr\Tests\TestCase;

class WorkingDayCalculatorTest extends TestCase
{
    public function test_counts_working_days_excluding_weekends_and_holidays(): void
    {
        Carbon::setTestNow('2026-03-02'); // Monday

        Holiday::query()->create([
            'branch_id' => null,
            'date' => '2026-03-04',
            'name' => 'Global',
            'type' => 'official',
        ]);

        Holiday::query()->create([
            'branch_id' => 1,
            'date' => '2026-03-04',
            'name' => 'Branch',
            'type' => 'official',
        ]);

        $calculator = new WorkingDayCalculator;

        // Mon 2, Tue 3, Wed 4 (holiday), Thu 5, Fri 6 (weekend off), Sat 7, Sun 8
        $this->assertSame(5, $calculator->count(
            Carbon::parse('2026-03-02'),
            Carbon::parse('2026-03-08'),
            1
        ));
    }

    public function test_branch_holidays_are_included_with_global_holidays(): void
    {
        Holiday::query()->create([
            'branch_id' => 2,
            'date' => '2026-03-03',
            'name' => 'Branch only',
            'type' => 'official',
        ]);

        $calculator = new WorkingDayCalculator;

        $this->assertTrue($calculator->isHoliday(Carbon::parse('2026-03-03'), 2));
        $this->assertFalse($calculator->isHoliday(Carbon::parse('2026-03-03'), 1));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
