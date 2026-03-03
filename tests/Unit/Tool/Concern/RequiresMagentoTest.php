<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool\Concern;

use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RequiresMagento;
use PHPUnit\Framework\TestCase;

class RequiresMagentoTest extends TestCase
{
    public function testRequireMagentoReturnsErrorWhenNotInitialized(): void
    {
        $wrapper = new class {
            use RequiresMagento;

            public function callRequireMagento(): ?array
            {
                return $this->requireMagento();
            }
        };

        $result = $wrapper->callRequireMagento();

        $this->assertIsArray($result);
        $this->assertTrue($result['error']);
        $this->assertStringContainsString('Magento not initialized', $result['message']);
    }
}
