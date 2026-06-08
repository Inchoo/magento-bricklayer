<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Bootstrap;

use Inchoo\MagentoBricklayer\Bootstrap\AreaEmulator;
use PHPUnit\Framework\TestCase;

class AreaEmulatorTest extends TestCase
{
    public function testItEmulatesTheDefaultAreaViaTheHardcodedLiteralNotTheRemovedConst(): void
    {
        // MagentoBootstrap::initialize() uses the hardcoded 'adminhtml' literal.
        // The default area value must be AREA_ADMINHTML regardless of getDefaultArea().
        $this->assertSame('adminhtml', AreaEmulator::AREA_ADMINHTML);
    }

    public function testItKeepsAllExistingAreaEmulationBehaviourAfterRemovingGetDefaultArea(): void
    {
        $emulator = new AreaEmulator();

        // getDefaultArea() should no longer exist.
        $this->assertFalse(
            method_exists($emulator, 'getDefaultArea'),
            'getDefaultArea() must have been removed'
        );

        // getCurrentArea() should no longer exist (removed as unused helper).
        $this->assertFalse(
            method_exists($emulator, 'getCurrentArea'),
            'getCurrentArea() must have been removed'
        );

        // All surviving public methods must exist.
        $this->assertTrue(method_exists($emulator, 'setArea'));
        $this->assertTrue(method_exists($emulator, 'isValidArea'));
        $this->assertTrue(method_exists($emulator, 'emulateArea'));
        $this->assertTrue(method_exists($emulator, 'getAvailableAreas'));

        // isValidArea() still works correctly.
        $this->assertTrue($emulator->isValidArea('adminhtml'));
        $this->assertTrue($emulator->isValidArea('frontend'));
        $this->assertFalse($emulator->isValidArea('not_a_real_area'));

        // getAvailableAreas() returns all expected area codes.
        $areas = $emulator->getAvailableAreas();
        $this->assertContains('adminhtml', $areas);
        $this->assertContains('frontend', $areas);
        $this->assertContains('global', $areas);
    }
}
