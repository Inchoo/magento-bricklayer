<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use Inchoo\MagentoBricklayer\Mcp\Tool\TimeUnits;
use PHPUnit\Framework\TestCase;

class TimeUnitsTest extends TestCase
{
    public function testItConvertsAMinutesSpecToSeconds(): void
    {
        $this->assertSame(300, TimeUnits::toSeconds('5m'));
        $this->assertSame(3600, TimeUnits::toSeconds('60m'));
        $this->assertSame(5400, TimeUnits::toSeconds('90m'));
    }

    public function testItConvertsAnHoursSpecToSeconds(): void
    {
        $this->assertSame(3600, TimeUnits::toSeconds('1h'));
        $this->assertSame(7200, TimeUnits::toSeconds('2h'));
        $this->assertSame(86400, TimeUnits::toSeconds('24h'));
    }

    public function testItConvertsADaysSpecToSeconds(): void
    {
        $this->assertSame(86400, TimeUnits::toSeconds('1d'));
        $this->assertSame(604800, TimeUnits::toSeconds('7d'));
    }

    public function testItDerivesTheCutoffTimestampFromTheSecondsValue(): void
    {
        $before = time();
        $result = TimeUnits::toCutoff('1h');
        $after = time();

        $this->assertNotNull($result);
        $this->assertGreaterThanOrEqual($before - 3600, $result);
        $this->assertLessThanOrEqual($after - 3600 + 1, $result);
    }

    public function testItDerivesTheHoursValueFromTheSecondsValue(): void
    {
        $this->assertSame(1, TimeUnits::toHours('5m'));
        $this->assertSame(1, TimeUnits::toHours('60m'));
        $this->assertSame(2, TimeUnits::toHours('61m'));
        $this->assertSame(1, TimeUnits::toHours('1h'));
        $this->assertSame(24, TimeUnits::toHours('24h'));
        $this->assertSame(24, TimeUnits::toHours('1d'));
        $this->assertSame(72, TimeUnits::toHours('3d'));
    }

    public function testItReturnsTheDefaultForAnUnparseableSpec(): void
    {
        $this->assertNull(TimeUnits::toSeconds('invalid'));
        $this->assertNull(TimeUnits::toSeconds(''));
        $this->assertNull(TimeUnits::toSeconds('5x'));
        $this->assertNull(TimeUnits::toSeconds('abc'));
    }
}
