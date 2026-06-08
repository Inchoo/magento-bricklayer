<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\TestCase;

/**
 * Tests that _skill_hint is present in success paths of introspection tool methods.
 *
 * Since these tools require Magento bootstrap, we verify via source code inspection
 * that the _skill_hint assignment exists in the success path (not error path).
 */
class SkillHintTest extends TestCase
{
    /**
     * @dataProvider skillHintMethodsProvider
     */
    public function testSkillHintPresentInSuccessPath(string $class, string $method, string $expectedHintFragment): void
    {
        $fqcn = 'Inchoo\\MagentoBricklayer\\Mcp\\Tool\\' . $class;
        $ref = new \ReflectionMethod($fqcn, $method);
        $source = file_get_contents($ref->getFileName());
        $startLine = $ref->getStartLine();
        $endLine = $ref->getEndLine();

        $lines = array_slice(explode("\n", $source), $startLine - 1, $endLine - $startLine + 1);
        $methodSource = implode("\n", $lines);

        $this->assertStringContainsString(
            '_skill_hint',
            $methodSource,
            "{$class}::{$method} should contain _skill_hint in its response"
        );

        $this->assertStringContainsString(
            $expectedHintFragment,
            $methodSource,
            "{$class}::{$method} _skill_hint should reference '{$expectedHintFragment}'"
        );
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function skillHintMethodsProvider(): array
    {
        return [
            'plugin-list' => ['ConfigurationTools', 'getPluginList', 'category=plugin'],
            'di-configuration' => ['ConfigurationTools', 'getDiConfiguration', 'category=module'],
            'preference-list' => ['ConfigurationTools', 'getPreferenceList', 'category=preference'],
            'event-list' => ['ConfigurationTools', 'getEventList', 'category=observer'],
            'eav-attributes' => ['EavTools', 'getEavAttributes', 'category=eav'],
            'database-schema' => ['DatabaseTools', 'getTableSchema', 'category=model'],
            'graphql-inspect' => ['GraphqlTools', 'inspectGraphql', 'category=graphql'],
            'diagnose-performance' => ['PerformanceTools', 'diagnosePerformance', 'category=performance'],
            'route-list' => ['RoutingTools', 'getRouteList', 'category=frontend'],
            'api-endpoints' => ['RoutingTools', 'getApiEndpoints', 'category=rest-api'],
        ];
    }

    /**
     * Verify _skill_hint is NOT in error return paths.
     *
     * @dataProvider errorReturnProvider
     */
    public function testSkillHintNotInErrorPath(string $class, string $method): void
    {
        $fqcn = 'Inchoo\\MagentoBricklayer\\Mcp\\Tool\\' . $class;
        $ref = new \ReflectionMethod($fqcn, $method);
        $source = file_get_contents($ref->getFileName());
        $startLine = $ref->getStartLine();
        $endLine = $ref->getEndLine();

        $lines = array_slice(explode("\n", $source), $startLine - 1, $endLine - $startLine + 1);
        $methodSource = implode("\n", $lines);

        // Find all error return lines and verify none contain _skill_hint.
        // Error returns are either the inline literal envelope or the centralized
        // RespondsWithErrors helpers (errorResponse()/runGuarded()).
        $errorLines = array_filter($lines, function ($line) {
            return str_contains($line, "'error' => true")
                || str_contains($line, '"error" => true')
                || str_contains($line, 'errorResponse(')
                || str_contains($line, 'runGuarded(');
        });

        foreach ($errorLines as $lineNum => $line) {
            // Check surrounding context (3 lines before and after)
            $contextStart = max(0, $lineNum - 3);
            $contextEnd = min(count($lines) - 1, $lineNum + 3);
            $context = array_slice($lines, $contextStart, $contextEnd - $contextStart + 1);
            $contextStr = implode("\n", $context);

            // This is a soft check — error paths are typically short return statements
            if (
                str_contains($contextStr, 'return')
                && (str_contains($contextStr, "'error'")
                    || str_contains($contextStr, 'errorResponse(')
                    || str_contains($contextStr, 'runGuarded('))
            ) {
                $this->assertStringNotContainsString(
                    '_skill_hint',
                    $contextStr,
                    "{$class}::{$method} should not have _skill_hint near error returns"
                );
            }
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function errorReturnProvider(): array
    {
        return [
            'plugin-list' => ['ConfigurationTools', 'getPluginList'],
            'di-configuration' => ['ConfigurationTools', 'getDiConfiguration'],
            'eav-attributes' => ['EavTools', 'getEavAttributes'],
        ];
    }
}
