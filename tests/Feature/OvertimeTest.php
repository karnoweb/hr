<?php

namespace Karnoweb\Hr\Tests\Feature;

use Carbon\Carbon;
use InvalidArgumentException;
use Karnoweb\Hr\Enums\DocumentStatus;
use Karnoweb\Hr\Enums\DocumentType;
use Karnoweb\Hr\Enums\OvertimeStatus;
use Karnoweb\Hr\Enums\OvertimeType;
use Karnoweb\Hr\Enums\PayrollPeriodStatus;
use Karnoweb\Hr\Facades\Hr;
use Karnoweb\Hr\Models\HrDocument;
use Karnoweb\Hr\Models\OvertimeRecord;
use Karnoweb\Hr\Models\PayrollPeriod;
use Karnoweb\Hr\Models\Shift;
use Karnoweb\Hr\Services\OvertimeService;
use Karnoweb\Hr\Tests\TestCase;

class OvertimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'hr.overtime.min_minutes' => 30,
            'hr.overtime.monthly_cap' => 120,
            'hr.overtime.requires_pre_approval' => false,
        ]);
    }

    public function test_clock_out_syncs_overtime_records_from_attendance(): void
    {
        $employee = $this->employeeWithDayShift();

        Hr::attendance()->clockIn($employee, '2026-03-02 08:00:00');
        Hr::attendance()->clockOut($employee, '2026-03-02 23:00:00');

        $regular = OvertimeRecord::query()
            ->where('employee_id', $employee->id)
            ->where('type', OvertimeType::Regular)
            ->first();

        $night = OvertimeRecord::query()
            ->where('employee_id', $employee->id)
            ->where('type', OvertimeType::Night)
            ->first();

        $this->assertSame(300, $regular?->calculated_minutes);
        $this->assertSame(60, $night?->calculated_minutes);
        $this->assertSame(OvertimeStatus::Pending, $regular?->status);
    }

    public function test_min_minutes_boundary_skips_small_overtime(): void
    {
        $employee = $this->employeeWithDayShift();

        Hr::attendance()->clockIn($employee, '2026-03-02 08:00:00');
        Hr::attendance()->clockOut($employee, '2026-03-02 17:20:00');

        $this->assertSame(0, OvertimeRecord::query()->where('employee_id', $employee->id)->count());
    }

    public function test_min_minutes_at_boundary_creates_record(): void
    {
        $employee = $this->employeeWithDayShift();

        Hr::attendance()->clockIn($employee, '2026-03-02 08:00:00');
        Hr::attendance()->clockOut($employee, '2026-03-02 17:30:00');

        $this->assertSame(1, OvertimeRecord::query()->where('employee_id', $employee->id)->count());
    }

    public function test_approve_rejects_when_monthly_cap_exceeded(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        OvertimeRecord::query()->create([
            'employee_id' => $employee->id,
            'date' => '2026-03-05',
            'calculated_minutes' => 90,
            'approved_minutes' => 90,
            'type' => OvertimeType::Regular,
            'status' => OvertimeStatus::Approved,
            'approved_at' => now(),
        ]);

        $pending = OvertimeRecord::query()->create([
            'employee_id' => $employee->id,
            'date' => '2026-03-10',
            'calculated_minutes' => 40,
            'type' => OvertimeType::Regular,
            'status' => OvertimeStatus::Pending,
        ]);

        $this->expectException(InvalidArgumentException::class);
        Hr::overtime()->approve($pending);
    }

    public function test_approved_minutes_for_period_groups_by_type(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        $period = PayrollPeriod::query()->create([
            'year' => 2026,
            'month' => 3,
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'working_days' => 22,
            'status' => PayrollPeriodStatus::Draft,
        ]);

        OvertimeRecord::query()->create([
            'employee_id' => $employee->id,
            'date' => '2026-03-02',
            'calculated_minutes' => 60,
            'approved_minutes' => 60,
            'type' => OvertimeType::Regular,
            'status' => OvertimeStatus::Approved,
        ]);

        OvertimeRecord::query()->create([
            'employee_id' => $employee->id,
            'date' => '2026-03-03',
            'calculated_minutes' => 45,
            'approved_minutes' => 45,
            'type' => OvertimeType::Night,
            'status' => OvertimeStatus::Approved,
        ]);

        $totals = Hr::overtime()->approvedMinutesForPeriod($employee, $period);

        $this->assertSame(60, $totals['regular']);
        $this->assertSame(45, $totals['night']);
        $this->assertSame(0, $totals['holiday']);
    }

    public function test_overtime_service_is_container_singleton(): void
    {
        $this->assertSame(
            $this->app->make(OvertimeService::class),
            Hr::overtime()
        );
    }

    public function test_requires_pre_approval_rejects_sync_without_document(): void
    {
        config(['hr.overtime.requires_pre_approval' => true]);

        $employee = $this->employeeWithDayShift();

        $this->expectException(InvalidArgumentException::class);

        Hr::attendance()->clockIn($employee, '2026-03-02 08:00:00');
        Hr::attendance()->clockOut($employee, '2026-03-02 17:30:00');
    }

    public function test_requires_pre_approval_allows_sync_with_approved_document(): void
    {
        config(['hr.overtime.requires_pre_approval' => true]);

        $employee = $this->employeeWithDayShift();

        $document = HrDocument::query()->create([
            'employee_id' => $employee->id,
            'type' => DocumentType::OvertimeApproval,
            'effective_date' => '2026-03-02',
            'status' => DocumentStatus::Approved,
        ]);

        Hr::attendance()->clockIn($employee, '2026-03-02 08:00:00');
        Hr::attendance()->clockOut($employee, '2026-03-02 17:30:00', [
            'hr_document_id' => $document->id,
        ]);

        $this->assertSame(1, OvertimeRecord::query()->where('employee_id', $employee->id)->count());
    }

    public function test_requires_pre_approval_rejects_document_effective_after_work_date(): void
    {
        config(['hr.overtime.requires_pre_approval' => true]);

        $employee = $this->employeeWithDayShift();

        $document = HrDocument::query()->create([
            'employee_id' => $employee->id,
            'type' => DocumentType::OvertimeApproval,
            'effective_date' => '2026-03-03',
            'status' => DocumentStatus::Approved,
        ]);

        $this->expectException(InvalidArgumentException::class);

        Hr::attendance()->clockIn($employee, '2026-03-02 08:00:00');
        Hr::attendance()->clockOut($employee, '2026-03-02 17:30:00', [
            'hr_document_id' => $document->id,
        ]);
    }

    protected function employeeWithDayShift()
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        $shift = Shift::query()->create([
            'branch_id' => 1,
            'code' => 'DAY-OT',
            'name' => 'Day',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'work_minutes' => 480,
        ]);

        Hr::shiftAssignments()->assignShift($employee, $shift->id, '2026-01-01');

        return $employee->fresh();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
