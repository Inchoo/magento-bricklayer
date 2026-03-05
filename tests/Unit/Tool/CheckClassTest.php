<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use Inchoo\MagentoBricklayer\Mcp\Tool\ConfigurationTools;
use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the check-class composite tool.
 */
class CheckClassTest extends TestCase
{
    public function testCheckClassToolExists(): void
    {
        $ref = new \ReflectionMethod(ConfigurationTools::class, 'checkClass');
        $attrs = $ref->getAttributes(McpTool::class);

        $this->assertNotEmpty($attrs, 'checkClass should have McpTool attribute');

        $instance = $attrs[0]->newInstance();
        $this->assertSame('check-class', $instance->name);
    }

    public function testCheckClassIsNotHidden(): void
    {
        $ref = new \ReflectionMethod(ConfigurationTools::class, 'checkClass');
        $attrs = $ref->getAttributes(McpTool::class);
        $instance = $attrs[0]->newInstance();

        $hidden = isset($instance->meta['hidden']) && $instance->meta['hidden'] === true;
        $this->assertFalse($hidden, 'check-class should be Tier 1 (not hidden)');
    }

    /**
     * Verify the method checks for empty className.
     * In unit tests, requireMagento() fires first, so we verify via source inspection.
     */
    public function testCheckClassValidatesEmptyClassName(): void
    {
        $ref = new \ReflectionMethod(ConfigurationTools::class, 'checkClass');
        $source = file_get_contents($ref->getFileName());
        $startLine = $ref->getStartLine();
        $endLine = $ref->getEndLine();

        $lines = array_slice(explode("\n", $source), $startLine - 1, $endLine - $startLine + 1);
        $methodSource = implode("\n", $lines);

        $this->assertStringContainsString(
            "className === ''",
            $methodSource,
            'checkClass should validate empty className'
        );
        $this->assertStringContainsString(
            'className is required',
            $methodSource,
            'checkClass should return error message for empty className'
        );
    }

    public function testCheckClassDescriptionUnder80Words(): void
    {
        $ref = new \ReflectionMethod(ConfigurationTools::class, 'checkClass');
        $attrs = $ref->getAttributes(McpTool::class);
        $instance = $attrs[0]->newInstance();

        $wordCount = str_word_count($instance->description);
        $this->assertLessThanOrEqual(80, $wordCount, "check-class description is {$wordCount} words (max 80)");
    }

    /**
     * Verify that check-class strips nested _skill_hint from sub-results.
     *
     * We test the structural contract: if plugins/di_configuration/preferences sub-keys
     * exist, they must NOT contain _skill_hint. The top-level _skill_hint must be present.
     *
     * Since the actual tool calls require Magento bootstrap, we verify via reflection
     * that the method source code contains unset() calls for _skill_hint.
     */
    public function testCheckClassStripsNestedSkillHints(): void
    {
        $ref = new \ReflectionMethod(ConfigurationTools::class, 'checkClass');
        $source = file_get_contents($ref->getFileName());
        $startLine = $ref->getStartLine();
        $endLine = $ref->getEndLine();

        $lines = array_slice(explode("\n", $source), $startLine - 1, $endLine - $startLine + 1);
        $methodSource = implode("\n", $lines);

        $this->assertStringContainsString(
            "unset(\$plugins['_skill_hint'])",
            $methodSource,
            'checkClass should strip _skill_hint from plugins sub-result'
        );
        $this->assertStringContainsString(
            "unset(\$di['_skill_hint'])",
            $methodSource,
            'checkClass should strip _skill_hint from di sub-result'
        );
        $this->assertStringContainsString(
            "unset(\$preferences['_skill_hint'])",
            $methodSource,
            'checkClass should strip _skill_hint from preferences sub-result'
        );
    }

    public function testCheckClassHasTopLevelSkillHint(): void
    {
        $ref = new \ReflectionMethod(ConfigurationTools::class, 'checkClass');
        $source = file_get_contents($ref->getFileName());
        $startLine = $ref->getStartLine();
        $endLine = $ref->getEndLine();

        $lines = array_slice(explode("\n", $source), $startLine - 1, $endLine - $startLine + 1);
        $methodSource = implode("\n", $lines);

        $this->assertStringContainsString(
            "\$result['_skill_hint']",
            $methodSource,
            'checkClass should set a top-level _skill_hint'
        );
    }
}
