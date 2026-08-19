<?php

namespace Karnoweb\Hr\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Karnoweb\Hr\Enums\AttendanceStatus;
use Karnoweb\Hr\Exceptions\HrException;
use Karnoweb\Hr\Facades\Hr;
use Karnoweb\Hr\Models\AttendanceRecord;
use Karnoweb\Hr\Models\EmployeeShiftAssignment;
use Karnoweb\Hr\Models\Holiday;
use Karnoweb\Hr\Models\Shift;
use Karnoweb\Hr\Models\ShiftPattern;
use Karnoweb\Hr\Services\AttendanceService;
use Karnoweb\Hr\Tests\TestCase;

class AttendanceShiftTest extends TestCase
{
    public function test_clock_in_and_out_compute_late_and_early_minutes(): void
    {
        Carbon::setTestNow('2026-03-02 08:20:00');

        $employee = $this->employeeWithShift([
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        config(['hr.attendance.late_tolerance_minutes' => 15]);
        config(['hr.attendance.early_leave_tolerance_minutes' => 15]);

        Hr::attendance()->clockIn($employee);
        $record = Hr::attendance()->clockOut($employee, '2026-03-02 16:40:00');

        $this->assertSame(20, $record->late_minutes);
        $this->assertSame(20, $record->early_leave_minutes);
        $this->assertGreaterThan(0, $record->work_minutes);
        $this->assertSame(AttendanceStatus::Present, $record->status);
    }

    public function test_tolerance_boundaries_are_not_late_or_early(): void
    {
        $employee = $this->employeeWithShift([
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        config(['hr.attendance.late_tolerance_minutes' => 15]);
        config(['hr.attendance.early_leave_tolerance_minutes' => 15]);

        Hr::attendance()->clockIn($employee, '2026-03-02 08:15:00');
        $record = Hr::attendance()->clockOut($employee, '2026-03-02 16:45:00');

        $this->assertSame(0, $record->late_minutes);
        $this->assertSame(0, $record->early_leave_minutes);
    }

    public function test_overnight_shift_uses_clock_in_date_for_unique_row(): void
    {
        $employee = $this->employeeWithShift([
            'start_time' => '22:00',
            'end_time' => '06:00',
            'is_night_shift' => true,
        ]);

        Hr::attendance()->clockIn($employee, '2026-01-15 22:00:00');
        $record = Hr::attendance()->clockOut($employee, '2026-01-16 06:00:00');

        $this->assertSame('2026-01-15', $record->date->toDateString());
        $this->assertSame(1, AttendanceRecord::query()->where('employee_id', $employee->id)->count());
    }

    public function test_duplicate_clock_in_is_rejected(): void
    {
        $employee = $this->employeeWithShift([
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        Hr::attendance()->clockIn($employee, '2026-03-02 08:00:00');

        $this->expectException(InvalidArgumentException::class);
        Hr::attendance()->clockIn($employee, '2026-03-02 08:05:00');
    }

    public function test_unique_employee_date_constraint_at_database_level(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser());

        AttendanceRecord::query()->create([
            'employee_id' => $employee->id,
            'date' => '2026-03-01',
            'status' => AttendanceStatus::Absent,
        ]);

        $this->expectException(QueryException::class);

        AttendanceRecord::query()->create([
            'employee_id' => $employee->id,
            'date' => '2026-03-01',
            'status' => AttendanceStatus::Absent,
        ]);
    }

    public function test_holiday_placeholder_and_clock_in_override(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        Holiday::query()->create([
            'branch_id' => 1,
            'date' => '2026-03-04',
            'name' => 'Branch holiday',
            'type' => 'official',
        ]);

        $placeholder = Hr::attendance()->ensureDayRecord($employee, '2026-03-04');
        $this->assertSame(AttendanceStatus::Holiday, $placeholder->status);

        $clocked = Hr::attendance()->clockIn($employee, '2026-03-04 09:00:00');
        $this->assertSame(AttendanceStatus::Present, $clocked->status);
    }

    public function test_weekend_placeholder_status(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser());

        $record = Hr::attendance()->ensureDayRecord($employee, '2026-03-06'); // Friday

        $this->assertSame(AttendanceStatus::Weekend, $record->status);
    }

    public function test_shift_assignment_closes_previous_active_row(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        $shiftA = Shift::query()->create([
            'branch_id' => 1,
            'code' => 'A',
            'name' => 'A',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'work_minutes' => 480,
        ]);

        $shiftB = Shift::query()->create([
            'branch_id' => 1,
            'code' => 'B',
            'name' => 'B',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'work_minutes' => 480,
        ]);

        $first = Hr::shiftAssignments()->assignShift($employee, $shiftA->id, '2026-01-01');
        $second = Hr::shiftAssignments()->assignShift($employee->fresh(), $shiftB->id, '2026-03-01');

        $first->refresh();
        $this->assertNotNull($first->end_date);
        $this->assertNull($first->current_key);
        $this->assertSame($employee->id, $second->current_key);
    }

    public function test_current_key_unique_constraint_for_shift_assignments(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser());

        EmployeeShiftAssignment::query()->create([
            'employee_id' => $employee->id,
            'shift_id' => Shift::query()->create([
                'code' => 'X1',
                'name' => 'X1',
                'start_time' => '08:00',
                'end_time' => '16:00',
                'work_minutes' => 480,
            ])->id,
            'effective_date' => '2026-01-01',
            'is_active' => true,
            'current_key' => $employee->id,
        ]);

        $this->expectException(QueryException::class);

        EmployeeShiftAssignment::query()->create([
            'employee_id' => $employee->id,
            'shift_id' => Shift::query()->create([
                'code' => 'X2',
                'name' => 'X2',
                'start_time' => '08:00',
                'end_time' => '16:00',
                'work_minutes' => 480,
            ])->id,
            'effective_date' => '2026-02-01',
            'is_active' => true,
            'current_key' => $employee->id,
        ]);
    }

    public function test_direct_second_current_key_insert_fails_at_database(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        $shiftA = Shift::query()->create([
            'branch_id' => 1,
            'code' => 'SA',
            'name' => 'SA',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'work_minutes' => 480,
        ]);

        Hr::shiftAssignments()->assignShift($employee, $shiftA->id, '2026-01-01');

        $shiftB = Shift::query()->create([
            'branch_id' => 1,
            'code' => 'SB',
            'name' => 'SB',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'work_minutes' => 480,
        ]);

        $this->expectException(QueryException::class);

        EmployeeShiftAssignment::query()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shiftB->id,
            'effective_date' => '2026-03-01',
            'is_active' => true,
            'current_key' => $employee->id,
        ]);
    }

    public function test_shift_pattern_structure_validation(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ShiftPattern::query()->create([
            'code' => 'BAD',
            'name' => 'Bad',
            'cycle_days' => 3,
            'pattern' => [
                ['day' => 0, 'shift_id' => null],
                ['day' => 1, 'shift_id' => null],
            ],
        ]);
    }

    public function test_branch_scoped_shift_codes_from_phase_two_migration(): void
    {
        Shift::query()->create([
            'branch_id' => 1,
            'code' => 'MORNING',
            'name' => 'Morning A',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'work_minutes' => 480,
        ]);

        Shift::query()->create([
            'branch_id' => 2,
            'code' => 'MORNING',
            'name' => 'Morning B',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'work_minutes' => 480,
        ]);

        $this->assertSame(2, Shift::query()->where('code', 'MORNING')->count());
    }

    public function test_manual_adjustment_records_audit_trail(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser());

        AttendanceRecord::query()->create([
            'employee_id' => $employee->id,
            'date' => '2026-03-02',
            'status' => AttendanceStatus::Present,
            'late_minutes' => 10,
        ]);

        $updated = Hr::attendance()->adjust($employee, '2026-03-02', ['late_minutes' => 0], 99);

        $this->assertSame(0, $updated->late_minutes);
        $this->assertCount(1, $updated->raw_data['adjustments']);
        $this->assertSame(99, $updated->raw_data['adjustments'][0]['by']);
    }

    public function test_adjust_with_require_approval_throws_hr_exception(): void
    {
        config(['hr.attendance.corrections.require_approval' => true]);

        $employee = Hr::employees()->createForUser($this->makeUser());

        AttendanceRecord::query()->create([
            'employee_id' => $employee->id,
            'date' => '2026-03-02',
            'status' => AttendanceStatus::Present,
        ]);

        $this->expectException(HrException::class);
        Hr::attendance()->adjust($employee, '2026-03-02', ['notes' => 'fix']);
    }

    public function test_auto_clock_out_command_closes_stale_records(): void
    {
        config(['hr.attendance.auto_clock_out' => true]);
        config(['hr.attendance.auto_clock_out_after_hours' => 12]);

        $employee = $this->employeeWithShift([
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        Carbon::setTestNow('2026-03-01 08:00:00');
        Hr::attendance()->clockIn($employee);

        Carbon::setTestNow('2026-03-01 22:00:00');

        Artisan::call('hr:auto-clock-out');

        $record = AttendanceRecord::query()->where('employee_id', $employee->id)->first();
        $this->assertNotNull($record->clock_out);
        $this->assertSame('auto', $record->source);
    }

    public function test_attendance_service_is_container_singleton(): void
    {
        $this->assertSame(
            $this->app->make(AttendanceService::class),
            Hr::attendance()
        );
    }

    protected function employeeWithShift(array $shiftAttributes)
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        $shift = Shift::query()->create(array_merge([
            'branch_id' => 1,
            'code' => 'T-'.uniqid(),
            'name' => 'Test Shift',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'work_minutes' => 480,
            'is_night_shift' => false,
        ], $shiftAttributes));

        Hr::shiftAssignments()->assignShift($employee, $shift->id, '2026-01-01');

        return $employee->fresh();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
