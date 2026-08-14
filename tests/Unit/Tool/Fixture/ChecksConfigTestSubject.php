<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool\Fixture;

use Inchoo\MagentoBricklayer\Config\ConfigLoader;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\ChecksConfig;

/**
 * Concrete class that uses ChecksConfig trait for testing purposes.
 */
class ChecksConfigTestSubject
{
    use ChecksConfig;

    /**
     * Override to inject a preconfigured ConfigLoader.
     */
    public function setConfigLoader(ConfigLoader $loader): void
    {
        $this->configLoader = $loader;
    }

    /**
     * Expose requireToolEnabled for testing.
     */
    public function testRequireToolEnabled(string $toolName): ?array
    {
        return $this->requireToolEnabled($toolName);
    }

    /**
     * Expose requireNonProduction for testing.
     * Note: isProductionMode() depends on MagentoBootstrap which cannot be easily
     * mocked without the full Magento framework, so we test the config lookup logic
     * indirectly through the method's behavior.
     */
    public function testRequireNonProduction(string $toolName): ?array
    {
        return $this->requireNonProduction($toolName);
    }

    /**
     * Expose isProductionMode for testing.
     */
    public function testIsProductionMode(): bool
    {
        return $this->isProductionMode();
    }
}
