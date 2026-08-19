<?php

namespace Karnoweb\Hr\Tests\Unit;

use Illuminate\Support\Carbon;
use Karnoweb\Hr\Facades\Hr;
use Karnoweb\Hr\Models\Shift;
use Karnoweb\Hr\Models\ShiftPattern;
use Karnoweb\Hr\Services\ShiftResolver;
use Karnoweb\Hr\Tests\TestCase;

class ShiftResolverTest extends TestCase
{
    public function test_resolves_fixed_shift_assignment(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        $shift = Shift::query()->create([
            'branch_id' => 1,
            'code' => 'DAY',
            'name' => 'Day Shift',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'work_minutes' => 480,
        ]);

        Hr::shiftAssignments()->assignShift($employee, $shift->id, '2026-01-01');

        $resolved = app(ShiftResolver::class)->resolve($employee->fresh(), Carbon::parse('2026-03-10'));

        $this->assertSame($shift->id, $resolved?->id);
    }

    public function test_resolves_rotating_pattern_for_seven_day_cycle(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        $morning = Shift::query()->create([
            'branch_id' => 1,
            'code' => 'AM',
            'name' => 'Morning',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'work_minutes' => 480,
        ]);

        $pattern = ShiftPattern::query()->create([
            'branch_id' => 1,
            'code' => 'ROT7',
            'name' => 'Rotate 7',
            'cycle_days' => 7,
            'pattern' => [
                ['day' => 0, 'shift_id' => $morning->id],
                ['day' => 1, 'shift_id' => null],
                ['day' => 2, 'shift_id' => $morning->id],
                ['day' => 3, 'shift_id' => null],
                ['day' => 4, 'shift_id' => $morning->id],
                ['day' => 5, 'shift_id' => null],
                ['day' => 6, 'shift_id' => null],
            ],
        ]);

        Hr::shiftAssignments()->assignPattern($employee, $pattern->id, '2026-03-01', '2026-03-01');

        $resolver = app(ShiftResolver::class);

        $this->assertSame($morning->id, $resolver->resolve($employee->fresh(), Carbon::parse('2026-03-01'))?->id);
        $this->assertNull($resolver->resolve($employee->fresh(), Carbon::parse('2026-03-02')));
        $this->assertSame($morning->id, $resolver->resolve($employee->fresh(), Carbon::parse('2026-03-08'))?->id);
    }

    public function test_resolves_fourteen_day_pattern_cycle(): void
    {
        $employee = Hr::employees()->createForUser($this->makeUser(), ['branch_id' => 1]);

        $shift = Shift::query()->create([
            'branch_id' => 1,
            'code' => 'LONG',
            'name' => 'Long',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
        ]);

        $patternDays = [];

        for ($day = 0; $day < 14; $day++) {
            $patternDays[] = ['day' => $day, 'shift_id' => $day === 13 ? $shift->id : null];
        }

        $pattern = ShiftPattern::query()->create([
            'branch_id' => 1,
            'code' => 'ROT14',
            'name' => 'Rotate 14',
            'cycle_days' => 14,
            'pattern' => $patternDays,
        ]);

        Hr::shiftAssignments()->assignPattern($employee, $pattern->id, '2026-03-01', '2026-03-01');

        $resolver = app(ShiftResolver::class);

        $this->assertNull($resolver->resolve($employee->fresh(), Carbon::parse('2026-03-01')));
        $this->assertSame($shift->id, $resolver->resolve($employee->fresh(), Carbon::parse('2026-03-14'))?->id);
    }
}
