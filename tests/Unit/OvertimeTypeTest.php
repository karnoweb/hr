<?php

namespace Karnoweb\Hr\Tests\Unit;

use Karnoweb\Hr\Enums\OvertimeType;
use Karnoweb\Hr\Tests\TestCase;

class OvertimeTypeTest extends TestCase
{
    public function test_rate_reads_configured_multipliers(): void
    {
        config([
            'hr.overtime.rates.regular' => 1.5,
            'hr.overtime.rates.holiday' => 2.0,
            'hr.overtime.rates.night' => 1.4,
        ]);

        $this->assertSame(1.5, OvertimeType::Regular->rate());
        $this->assertSame(2.0, OvertimeType::Holiday->rate());
        $this->assertSame(1.4, OvertimeType::Night->rate());
    }
}
