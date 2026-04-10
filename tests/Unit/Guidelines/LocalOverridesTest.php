<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Guidelines;

use Inchoo\MagentoBricklayer\Guidelines\GuidelinesCompiler;
use Inchoo\MagentoBricklayer\Guidelines\LocalOverrideHelper;
use Inchoo\MagentoBricklayer\Mcp\Tool\ContextTools;
use PHPUnit\Framework\TestCase;

class LocalOverridesTest extends TestCase
{
    private string $magentoRoot;
    private string $packageRoot;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/bricklayer_overrides_' . uniqid();
        $this->magentoRoot = $base . '/project';
        $this->packageRoot = $base . '/package';
        mkdir($this->magentoRoot, 0755, true);
        mkdir($this->packageRoot . '/config/guidelines/patterns', 0755, true);
        mkdir($this->packageRoot . '/config/skills/plugin', 0755, true);
        file_put_contents(
            $this->packageRoot . '/config/guidelines/patterns/plugin.md',
            '# Bundled Plugin Guideline'
        );
        file_put_contents(
            $this->packageRoot . '/config/skills/plugin/SKILL.md',
            '# Bundled Plugin Skill'
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(dirname($this->magentoRoot));
    }

    public function testAppendsProjectContextWhenFileExists(): void
    {
        mkdir($this->magentoRoot . '/.bricklayer', 0755, true);
        file_put_contents(
            $this->magentoRoot . '/.bricklayer/project-context.md',
            "We use ERP sync via RabbitMQ queues."
        );

        $compiler = new GuidelinesCompiler($this->magentoRoot, $this->packageRoot);
        $output = $compiler->compile('claude-code');

        $this->assertStringContainsString('## Project-Specific Context', $output);
        $this->assertStringContainsString('We use ERP sync via RabbitMQ queues.', $output);
        $this->assertContains('.bricklayer/project-context.md', $compiler->getAppliedOverrides());
    }

    public function testDoesNotAppendSectionWhenProjectContextAbsent(): void
    {
        $compiler = new GuidelinesCompiler($this->magentoRoot, $this->packageRoot);
        $output = $compiler->compile('claude-code');

        $this->assertStringNotContainsString('## Project-Specific Context', $output);
    }

    public function testDoesNotAppendSectionWhenProjectContextEmpty(): void
    {
        mkdir($this->magentoRoot . '/.bricklayer', 0755, true);
        file_put_contents($this->magentoRoot . '/.bricklayer/project-context.md', "   \n\n");

        $compiler = new GuidelinesCompiler($this->magentoRoot, $this->packageRoot);
        $output = $compiler->compile('claude-code');

        $this->assertStringNotContainsString('## Project-Specific Context', $output);
    }

    public function testMergesValidDecisionMatrixRowsAndSkipsMalformed(): void
    {
        mkdir($this->magentoRoot . '/.bricklayer', 0755, true);
        file_put_contents(
            $this->magentoRoot . '/.bricklayer/decision-matrix.md',
            "| Modifying ERP sync | `code-runner` | `development-context category=erp` |\n"
            . "this is not a row\n"
            . "|---|---|---|\n"
            . "| Checkout steps | `plugin-list` | `development-context category=checkout` |\n"
        );

        $compiler = new GuidelinesCompiler($this->magentoRoot, $this->packageRoot);
        $output = $compiler->compile('claude-code');

        $this->assertStringContainsString('Modifying ERP sync', $output);
        $this->assertStringContainsString('Checkout steps', $output);
        $this->assertStringNotContainsString('this is not a row', $output);
        $this->assertContains('.bricklayer/decision-matrix.md', $compiler->getAppliedOverrides());
    }

    public function testDoesNotMergeDecisionMatrixWhenFileAbsent(): void
    {
        $compiler = new GuidelinesCompiler($this->magentoRoot, $this->packageRoot);
        $output = $compiler->compile('claude-code');

        $this->assertStringNotContainsString('Modifying ERP sync', $output);
        $this->assertNotContains('.bricklayer/decision-matrix.md', $compiler->getAppliedOverrides());
    }

    public function testResolveGuidelinePathReturnsLocalWhenOverrideExists(): void
    {
        mkdir($this->magentoRoot . '/.bricklayer/guidelines/patterns', 0755, true);
        $localPath = $this->magentoRoot . '/.bricklayer/guidelines/patterns/plugin.md';
        file_put_contents($localPath, '# Local Plugin Guideline');

        $context = new ContextTools($this->magentoRoot, $this->packageRoot);
        $resolved = $context->resolveGuidelinePath('patterns/plugin.md');

        $this->assertSame($localPath, $resolved);
    }

    public function testResolveGuidelinePathReturnsBundledWhenNoLocalOverride(): void
    {
        $context = new ContextTools($this->magentoRoot, $this->packageRoot);
        $resolved = $context->resolveGuidelinePath('patterns/plugin.md');

        $this->assertSame(
            $this->packageRoot . '/config/guidelines/patterns/plugin.md',
            $resolved
        );
    }

    public function testLocalOverrideHelperStripsFrontmatter(): void
    {
        $content = "---\nname: Test\ndescription: Hello world\n---\n\n# Body\n\nContent here.";
        $stripped = LocalOverrideHelper::stripFrontmatter($content);

        $this->assertStringStartsWith('# Body', $stripped);
        $this->assertStringNotContainsString('name: Test', $stripped);
    }

    public function testLocalOverrideHelperLeavesContentUnchangedWhenNoFrontmatter(): void
    {
        $content = "# Title\n\nParagraph.";
        $stripped = LocalOverrideHelper::stripFrontmatter($content);

        $this->assertSame($content, $stripped);
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
