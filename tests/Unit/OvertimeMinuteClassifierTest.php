<?php

namespace Karnoweb\Hr\Tests\Unit;

use Carbon\Carbon;
use Karnoweb\Hr\Support\OvertimeMinuteClassifier;
use Karnoweb\Hr\Tests\TestCase;

class OvertimeMinuteClassifierTest extends TestCase
{
    public function test_classifies_night_window_crossing_midnight(): void
    {
        config([
            'hr.overtime.night_start' => '22:00',
            'hr.overtime.night_end' => '06:00',
        ]);

        $classifier = new OvertimeMinuteClassifier;
        $date = Carbon::parse('2026-03-02');

        $breakdown = $classifier->classify(
            Carbon::parse('2026-03-02 17:00:00'),
            Carbon::parse('2026-03-02 23:00:00'),
            $date,
            false
        );

        $this->assertSame(360, $breakdown['total']);
        $this->assertSame(60, $breakdown['night']);
        $this->assertSame(300, $breakdown['regular']);
        $this->assertSame(0, $breakdown['holiday']);
    }

    public function test_holiday_non_night_minutes_go_to_holiday_bucket(): void
    {
        $classifier = new OvertimeMinuteClassifier;
        $date = Carbon::parse('2026-03-02');

        $breakdown = $classifier->classify(
            Carbon::parse('2026-03-02 17:00:00'),
            Carbon::parse('2026-03-02 19:00:00'),
            $date,
            true
        );

        $this->assertSame(120, $breakdown['total']);
        $this->assertSame(0, $breakdown['regular']);
        $this->assertSame(120, $breakdown['holiday']);
    }
}
