<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Config;

use Inchoo\MagentoBricklayer\Config\ConfigInitializer;
use Inchoo\MagentoBricklayer\Config\ConfigValidator;
use Inchoo\MagentoBricklayer\Config\IteratesToolPhpFiles;
use PHPUnit\Framework\TestCase;

class ToolPhpFileIteratorTest extends TestCase
{
    protected function setUp(): void
    {
        ConfigValidator::clearKnownToolsCache();
        ConfigInitializer::clearConfigurableToolsCache();
    }

    protected function tearDown(): void
    {
        ConfigValidator::clearKnownToolsCache();
        ConfigInitializer::clearConfigurableToolsCache();
    }

    /**
     * it walks tool php files through one shared iterator
     */
    public function testItWalksToolPhpFilesThroughOneSharedIterator(): void
    {
        // Both classes must use the shared trait
        $validatorUses = class_uses(ConfigValidator::class, false) ?: [];
        $initializerUses = class_uses(ConfigInitializer::class, false) ?: [];

        $this->assertContains(
            IteratesToolPhpFiles::class,
            $validatorUses,
            'ConfigValidator must use the IteratesToolPhpFiles trait'
        );
        $this->assertContains(
            IteratesToolPhpFiles::class,
            $initializerUses,
            'ConfigInitializer must use the IteratesToolPhpFiles trait'
        );
    }

    /**
     * it clears the known-tools and configurable-tools caches between tests
     */
    public function testItClearsTheKnownToolsAndConfigurableToolsCachesBetweenTests(): void
    {
        // First call populates cache
        $tools1 = ConfigValidator::getKnownTools();
        $this->assertNotEmpty($tools1);

        // clearKnownToolsCache works — a second call after clearing still returns the same list
        ConfigValidator::clearKnownToolsCache();
        $tools2 = ConfigValidator::getKnownTools();
        $this->assertEquals($tools1, $tools2);

        // Same for configurable tools
        $config1 = ConfigInitializer::discoverConfigurableTools();
        ConfigInitializer::clearConfigurableToolsCache();
        $config2 = ConfigInitializer::discoverConfigurableTools();
        $this->assertEquals($config1, $config2);
    }

    public function testCacheIsUsedOnSubsequentCalls(): void
    {
        ConfigValidator::clearKnownToolsCache();

        $first = ConfigValidator::getKnownTools();
        $second = ConfigValidator::getKnownTools();

        $this->assertSame($first, $second, 'Cached result must be identical on repeated calls');
    }
}
