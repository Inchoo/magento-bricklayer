<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use Inchoo\MagentoBricklayer\Guidelines\GuidelinesCompiler;
use Inchoo\MagentoBricklayer\Mcp\Tool\ContextTools;
use PHPUnit\Framework\TestCase;

class LocalSkillResolutionTest extends TestCase
{
    private string $magentoRoot;
    private string $packageRoot;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/bricklayer_skill_resolution_' . uniqid();
        $this->magentoRoot = $base . '/project';
        $this->packageRoot = $base . '/package';
        mkdir($this->magentoRoot, 0755, true);
        mkdir($this->packageRoot . '/config/skills/plugin', 0755, true);
        mkdir($this->packageRoot . '/config/guidelines', 0755, true);
        file_put_contents(
            $this->packageRoot . '/config/skills/plugin/SKILL.md',
            "# Bundled Plugin Skill\n\nBundled plugin content."
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(dirname($this->magentoRoot));
    }

    public function testLocalSkillOverridesBundledWithFrontmatterStripped(): void
    {
        $dir = $this->magentoRoot . '/.bricklayer/skills/plugin';
        mkdir($dir, 0755, true);
        file_put_contents(
            $dir . '/SKILL.md',
            "---\nname: Local Plugin\ndescription: Project override for plugin skill.\n---\n\n"
            . "# Local Plugin Content\n\nProject-specific plugin guidance."
        );

        $context = new ContextTools($this->magentoRoot, $this->packageRoot);
        $result = $context->getDevelopmentContext('plugin');

        $this->assertArrayNotHasKey('error', $result);
        $this->assertStringContainsString('Local Plugin Content', $result['skills']);
        $this->assertStringContainsString('Project-specific plugin guidance', $result['skills']);
        $this->assertStringNotContainsString('name: Local Plugin', $result['skills']);
        $this->assertStringNotContainsString('Bundled plugin content.', $result['skills']);
        $this->assertStringContainsString('[Project]', $result['skills']);
    }

    public function testFallsBackToBundledWhenNoLocalOverride(): void
    {
        $context = new ContextTools($this->magentoRoot, $this->packageRoot);
        $result = $context->getDevelopmentContext('plugin');

        $this->assertArrayNotHasKey('error', $result);
        $this->assertStringContainsString('Bundled plugin content.', $result['skills']);
        $this->assertStringNotContainsString('[Project]', $result['skills']);
    }

    public function testNewLocalOnlySkillBecomesCallable(): void
    {
        $dir = $this->magentoRoot . '/.bricklayer/skills/csp-scripts';
        mkdir($dir, 0755, true);
        file_put_contents(
            $dir . '/SKILL.md',
            "---\nname: CSP Scripts\ndescription: Manage Content Security Policy scripts.\n---\n\n"
            . "# CSP Scripts\n\nUse nonces, avoid unsafe-inline."
        );

        $context = new ContextTools($this->magentoRoot, $this->packageRoot);
        $result = $context->getDevelopmentContext('csp-scripts');

        $this->assertArrayNotHasKey('error', $result);
        $this->assertSame('csp-scripts', $result['category']);
        $this->assertStringContainsString('Manage Content Security Policy', $result['description']);
        $this->assertStringContainsString('Use nonces', $result['skills']);
        $this->assertStringNotContainsString('description: Manage Content', $result['skills']);
    }

    public function testUnknownCategoryReturnsError(): void
    {
        $context = new ContextTools($this->magentoRoot, $this->packageRoot);
        $result = $context->getDevelopmentContext('does-not-exist');

        $this->assertArrayHasKey('error', $result);
        $this->assertTrue($result['error']);
    }

    public function testNewLocalSkillAppearsInCategoriesTable(): void
    {
        $dir = $this->magentoRoot . '/.bricklayer/skills/csp-scripts';
        mkdir($dir, 0755, true);
        file_put_contents(
            $dir . '/SKILL.md',
            "---\nname: CSP Scripts\ndescription: Manage CSP scripts.\n---\n\n# CSP"
        );

        $compiler = new GuidelinesCompiler($this->magentoRoot, $this->packageRoot);
        $output = $compiler->compile('claude-code');

        $this->assertStringContainsString('**Project-specific**', $output);
        $this->assertStringContainsString('`csp-scripts`', $output);
        $this->assertStringContainsString('Manage CSP scripts', $output);
    }

    public function testOverrideSkillDoesNotAddProjectSpecificRow(): void
    {
        // Local skill has the same category as a bundled skill → override.
        $dir = $this->magentoRoot . '/.bricklayer/skills/plugin';
        mkdir($dir, 0755, true);
        file_put_contents(
            $dir . '/SKILL.md',
            "---\nname: Local Plugin\ndescription: Project plugin guidance.\n---\n\n# Local"
        );

        $compiler = new GuidelinesCompiler($this->magentoRoot, $this->packageRoot);
        $output = $compiler->compile('claude-code');

        // No project-specific row for the override (bundled row already covers it).
        $this->assertStringNotContainsString('**Project-specific**', $output);
        // But the override is still tracked.
        $this->assertContains(
            '.bricklayer/skills/plugin/SKILL.md',
            $compiler->getAppliedOverrides()
        );
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
