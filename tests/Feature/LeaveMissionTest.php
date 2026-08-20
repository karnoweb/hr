<?php

namespace Karnoweb\Hr\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Karnoweb\Hr\Enums\AttendanceStatus;
use Karnoweb\Hr\Enums\DocumentStatus;
use Karnoweb\Hr\Enums\DocumentType;
use Karnoweb\Hr\Enums\LeaveRequestStatus;
use Karnoweb\Hr\Exceptions\InsufficientLeaveBalanceException;
use Karnoweb\Hr\Facades\Hr;
use Karnoweb\Hr\Models\AttendanceRecord;
use Karnoweb\Hr\Models\Holiday;
use Karnoweb\Hr\Models\HrDocument;
use Karnoweb\Hr\Models\LeaveBalance;
use Karnoweb\Hr\Services\LeaveBalanceService;
use Karnoweb\Hr\Services\MissionService;
use Karnoweb\Hr\Tests\TestCase;

class LeaveMissionTest extends TestCase
{
    public function test_leave_request_rejects_insufficient_balance(): void
    {
        $employee = $this->employeeWithAnnualBalance(2026, entitled: 5, remaining: 5);

        $this->expectException(InsufficientLeaveBalanceException::class);
        Hr::leave()->request($employee, [
            'type' => 'annual',
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-10',
        ]);
    }

    public function test_second_pending_request_rejected_when_combined_exceeds_balance(): void
    {
        $employee = $this->employeeWithAnnualBalance(2026, entitled: 5, remaining: 5);

        Hr::leave()->request($employee, [
            'type' => 'annual',
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-02',
            'days' => 3,
        ], ['use_calculated_days' => false]);

        $this->expectException(InsufficientLeaveBalanceException::class);
        Hr::leave()->request($employee->fresh(), [
            'type' => 'annual',
            'start_date' => '2026-03-03',
            'end_date' => '2026-03-03',
            'days' => 3,
        ], ['use_calculated_days' => false]);
    }

    public function test_leave_overlap_rules(): void
    {
        $employee = $this->employeeWithAnnualBalance(2026, entitled: 20, remaining: 20);

        Hr::leave()->request($employee, [
            'type' => 'annual',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-05',
            'days' => 3,
        ], ['use_calculated_days' => false]);

        $this->expectException(InvalidArgumentException::class);
        Hr::leave()->request($employee->fresh(), [
            'type' => 'annual',
            'start_date' => '2026-04-04',
            'end_date' => '2026-04-07',
            'days' => 2,
        ], ['use_calculated_days' => false]);
    }

    public function test_adjacent_leave_ranges_are_allowed(): void
    {
        $employee = $this->employeeWithAnnualBalance(2026, entitled: 20, remaining: 20);

        Hr::leave()->request($employee, [
            'type' => 'annual',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-02',
            'days' => 2,
        ], ['use_calculated_days' => false]);

        $second = Hr::leave()->request($employee->fresh(), [
            'type' => 'annual',
            'start_date' => '2026-04-03',
            'end_date' => '2026-04-04',
            'days' => 2,
        ], ['use_calculated_days' => false]);

        $this->assertSame(LeaveRequestStatus::Pending, $second->status);
    }

    public function test_working_day_count_excludes_weekends_and_holidays(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
        $this->seedAnnualBalance($employee, 2026, 20, 20);

        Holiday::query()->create([
            'branch_id' => 1,
            'date' => '2026-03-04',
            'name' => 'Holiday',
            'type' => 'official',
        ]);

        $request = Hr::leave()->request($employee, [
            'type' => 'annual',
            'start_date' => '2026-03-02', // Mon
            'end_date' => '2026-03-08',   // Sun (Fri off)
        ]);

        $this->assertSame(5.0, (float) $request->days);
    }

    public function test_marriage_leave_enforces_fixed_days_and_once_per_employment(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser());

        Hr::leave()->request($employee, [
            'type' => 'marriage',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-03',
            'days' => 3,
        ], ['use_calculated_days' => false]);

        $this->expectException(InvalidArgumentException::class);
        Hr::leave()->request($employee->fresh(), [
            'type' => 'marriage',
            'start_date' => '2027-01-01',
            'end_date' => '2027-01-03',
            'days' => 3,
        ], ['use_calculated_days' => false]);
    }

    public function test_hourly_leave_enforces_monthly_cap(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser());

        Hr::leave()->request($employee, [
            'type' => 'hourly',
            'start_date' => '2026-03-10',
            'end_date' => '2026-03-10',
            'hours' => 8,
        ], ['use_calculated_days' => false]);

        $this->expectException(InvalidArgumentException::class);
        Hr::leave()->request($employee->fresh(), [
            'type' => 'hourly',
            'start_date' => '2026-03-15',
            'end_date' => '2026-03-15',
            'hours' => 5,
        ], ['use_calculated_days' => false]);
    }

    public function test_sick_leave_approval_requires_document_attachment(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser());

        $document = HrDocument::query()->create([
            'employee_id' => $employee->id,
            'type' => DocumentType::Leave,
            'effective_date' => '2026-03-01',
            'status' => DocumentStatus::Draft,
        ]);

        $request = Hr::leave()->request($employee, [
            'type' => 'sick',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-02',
            'days' => 2,
            'hr_document_id' => $document->id,
        ], ['use_calculated_days' => false]);

        $this->expectException(InvalidArgumentException::class);
        Hr::leave()->approve($request);
    }

    public function test_approve_decrements_balance_and_marks_attendance(): void
    {
        Carbon::setTestNow('2026-02-01');
        $employee = $this->employeeWithAnnualBalance(2026, entitled: 10, remaining: 10);

        $request = Hr::leave()->request($employee, [
            'type' => 'annual',
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-03',
            'days' => 2,
        ], ['use_calculated_days' => false]);

        Hr::leave()->approve($request);

        $balance = Hr::leave()->balance($employee->fresh(), 2026, 'annual');
        $this->assertSame(8.0, (float) $balance->remaining_days);

        $record = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', '2026-03-02')
            ->first();

        $this->assertSame(AttendanceStatus::Leave, $record->status);
    }

    public function test_cancel_future_approved_leave_restores_balance(): void
    {
        Carbon::setTestNow('2026-02-01');
        $employee = $this->employeeWithAnnualBalance(2026, entitled: 10, remaining: 10);

        $request = Hr::leave()->request($employee, [
            'type' => 'annual',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-02',
            'days' => 2,
        ], ['use_calculated_days' => false]);

        Hr::leave()->approve($request);
        Hr::leave()->cancel($request->fresh());

        $balance = Hr::leave()->balance($employee->fresh(), 2026, 'annual');
        $this->assertSame(10.0, (float) $balance->remaining_days);
        $this->assertSame(LeaveRequestStatus::Cancelled, $request->fresh()->status);
    }

    public function test_carry_over_caps_at_configured_maximum(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser());

        LeaveBalance::query()->create([
            'employee_id' => $employee->id,
            'year' => 2025,
            'type' => 'annual',
            'entitled_days' => 26,
            'used_days' => 10,
            'carried_days' => 0,
            'adjustment_days' => 0,
            'remaining_days' => 20,
        ]);

        app(LeaveBalanceService::class)->carryOverYear(2025, 2026);

        $next = Hr::leave()->balance($employee, 2026, 'annual');
        $this->assertSame(9.0, (float) $next->carried_days);
        $this->assertSame(35.0, (float) $next->remaining_days);
    }

    public function test_terminate_applies_forfeit_policy_to_balances(): void
    {
        config(['hr.leave.termination.balance_policy' => 'forfeit']);

        $employee = $this->employeeWithAnnualBalance(2026, entitled: 10, remaining: 7);

        Hr::employees()->terminate($employee);

        $balance = Hr::leave()->balance($employee->fresh(), 2026, 'annual');
        $this->assertSame(0.0, (float) $balance->remaining_days);
    }

    public function test_mission_rejects_overlap_with_leave(): void
    {
        $employee = $this->employeeWithAnnualBalance(2026, entitled: 20, remaining: 20);

        Hr::leave()->request($employee, [
            'type' => 'annual',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-05',
            'days' => 3,
        ], ['use_calculated_days' => false]);

        $this->expectException(InvalidArgumentException::class);
        Hr::missions()->request($employee->fresh(), [
            'start_date' => '2026-06-04',
            'end_date' => '2026-06-07',
            'destination' => 'Tehran',
            'purpose' => 'Meeting',
        ]);
    }

    public function test_mission_approval_marks_attendance_and_can_create_document(): void
    {
        Carbon::setTestNow('2026-02-01');
        $employee = Hr::employees()->createForUser($this->makeUser());

        $mission = Hr::missions()->request($employee, [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-02',
            'destination' => 'Isfahan',
            'purpose' => 'Training',
            'days' => 2,
        ], ['create_document' => true, 'use_calculated_days' => false]);

        $this->assertNotNull($mission->hr_document_id);

        Hr::missions()->approve($mission);

        $record = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', '2026-04-01')
            ->first();

        $this->assertSame(AttendanceStatus::Mission, $record->status);
    }

    public function test_carry_over_command_runs(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser());

        LeaveBalance::query()->create([
            'employee_id' => $employee->id,
            'year' => 2025,
            'type' => 'annual',
            'entitled_days' => 26,
            'used_days' => 20,
            'remaining_days' => 6,
        ]);

        Artisan::call('hr:carry-over-leave-balances', ['fromYear' => 2025, 'toYear' => 2026]);

        $this->assertNotNull(Hr::leave()->balance($employee, 2026, 'annual'));
    }

    public function test_mission_approval_revalidates_leave_overlap(): void
    {
        $employee = $this->employeeWithAnnualBalance(2026, entitled: 20, remaining: 20);

        $mission = Hr::missions()->request($employee, [
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-12',
            'destination' => 'Shiraz',
            'purpose' => 'Visit',
            'days' => 3,
        ], ['use_calculated_days' => false]);

        Hr::leave()->request($employee->fresh(), [
            'type' => 'annual',
            'start_date' => '2026-06-11',
            'end_date' => '2026-06-11',
            'days' => 1,
        ], ['use_calculated_days' => false]);

        $this->expectException(InvalidArgumentException::class);
        Hr::missions()->approve($mission->fresh());
    }

    public function test_pending_leave_reserve_splits_across_years(): void
    {
        $employee = $this->employeeWithAnnualBalance(2025, entitled: 5, remaining: 5);
        $this->seedAnnualBalance($employee, 2026, 5, 5);

        Hr::leave()->request($employee, [
            'type' => 'annual',
            'start_date' => '2025-12-29',
            'end_date' => '2026-01-03',
            'days' => 6,
        ], ['use_calculated_days' => false]);

        $reserved2025 = app(LeaveBalanceService::class)->pendingReservedDays($employee, 2025, 'annual');
        $reserved2026 = app(LeaveBalanceService::class)->pendingReservedDays($employee, 2026, 'annual');

        $this->assertGreaterThan(0, $reserved2025);
        $this->assertGreaterThan(0, $reserved2026);
        $this->assertEqualsWithDelta(6.0, $reserved2025 + $reserved2026, 0.01);
    }

    public function test_mission_service_is_container_singleton(): void
    {
        $this->assertSame(
            $this->app->make(MissionService::class),
            Hr::missions()
        );
    }

    protected function employeeWithAnnualBalance(int $year, float $entitled, float $remaining)
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);
        $this->seedAnnualBalance($employee, $year, $entitled, $remaining);

        return $employee;
    }

    protected function seedAnnualBalance($employee, int $year, float $entitled, float $remaining): void
    {
        LeaveBalance::query()->create([
            'employee_id' => $employee->id,
            'year' => $year,
            'type' => 'annual',
            'entitled_days' => $entitled,
            'used_days' => $entitled - $remaining,
            'carried_days' => 0,
            'adjustment_days' => 0,
            'remaining_days' => $remaining,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
