<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool\Concern;

use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RequiresValidVerbosity;
use PHPUnit\Framework\TestCase;

class RequiresValidVerbosityTest extends TestCase
{
    /**
     * it validates the verbosity enum through one concern
     */
    public function testItValidatesTheVerbosityEnumThroughOneConcern(): void
    {
        $subject = new class {
            use RequiresValidVerbosity;

            /** @return array<string, mixed>|null */
            public function callRequireValidVerbosity(string $verbosity): ?array
            {
                return $this->requireValidVerbosity($verbosity);
            }
        };

        // Valid values return null
        $this->assertNull($subject->callRequireValidVerbosity('minimal'));
        $this->assertNull($subject->callRequireValidVerbosity('standard'));
        $this->assertNull($subject->callRequireValidVerbosity('detailed'));

        // Invalid value returns canonical error envelope
        $result = $subject->callRequireValidVerbosity('verbose');
        $this->assertIsArray($result);
        $this->assertTrue($result['error']);
        $this->assertSame(
            'verbosity must be one of: minimal, standard, detailed',
            $result['message']
        );
    }

    public function testItReturnsNullForAllValidVerbosityLevels(): void
    {
        $subject = new class {
            use RequiresValidVerbosity;

            /** @return array<string, mixed>|null */
            public function check(string $v): ?array
            {
                return $this->requireValidVerbosity($v);
            }
        };

        foreach (['minimal', 'standard', 'detailed'] as $level) {
            $this->assertNull($subject->check($level), "Expected null for verbosity '$level'");
        }
    }

    public function testItReturnsAnErrorEnvelopeForAnInvalidVerbosityLevel(): void
    {
        $subject = new class {
            use RequiresValidVerbosity;

            /** @return array<string, mixed>|null */
            public function check(string $v): ?array
            {
                return $this->requireValidVerbosity($v);
            }
        };

        $result = $subject->check('invalid');

        $this->assertNotNull($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertTrue($result['error']);
    }

    public function testModuleToolsUsesTheRequiresValidVerbosityConcern(): void
    {
        $uses = class_uses(\Inchoo\MagentoBricklayer\Mcp\Tool\ModuleTools::class);
        $this->assertContains(RequiresValidVerbosity::class, $uses);
    }

    public function testEavToolsUsesTheRequiresValidVerbosityConcern(): void
    {
        $uses = class_uses(\Inchoo\MagentoBricklayer\Mcp\Tool\EavTools::class);
        $this->assertContains(RequiresValidVerbosity::class, $uses);
    }

    public function testDiagnosticToolsUsesTheRequiresValidVerbosityConcern(): void
    {
        $uses = class_uses(\Inchoo\MagentoBricklayer\Mcp\Tool\DiagnosticTools::class);
        $this->assertContains(RequiresValidVerbosity::class, $uses);
    }
}
