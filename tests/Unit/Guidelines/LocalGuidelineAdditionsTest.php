<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Guidelines;

use Inchoo\MagentoBricklayer\Guidelines\GuidelinesCompiler;
use PHPUnit\Framework\TestCase;

class LocalGuidelineAdditionsTest extends TestCase
{
    private string $magentoRoot;
    private string $packageRoot;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/bricklayer_additions_' . uniqid();
        $this->magentoRoot = $base . '/project';
        $this->packageRoot = $base . '/package';
        mkdir($this->magentoRoot, 0755, true);
        mkdir($this->packageRoot . '/config/guidelines/patterns', 0755, true);
        mkdir($this->packageRoot . '/config/skills', 0755, true);
        file_put_contents(
            $this->packageRoot . '/config/guidelines/patterns/plugin.md',
            '# Bundled Plugin Guideline'
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(dirname($this->magentoRoot));
    }

    public function testNewLocalGuidelineAppearsAsSection(): void
    {
        $dir = $this->magentoRoot . '/.bricklayer/guidelines/project';
        mkdir($dir, 0755, true);
        file_put_contents(
            $dir . '/csp-scripts.md',
            "Local CSP scripts conventions.\n\n- Use nonces.\n- Never use unsafe-inline."
        );

        $compiler = new GuidelinesCompiler($this->magentoRoot, $this->packageRoot);
        $output = $compiler->compile('claude-code');

        $this->assertStringContainsString('## Project', $output);
        $this->assertStringContainsString('### Csp Scripts', $output);
        $this->assertStringContainsString('Local CSP scripts conventions.', $output);
        $this->assertContains(
            '.bricklayer/guidelines/project/csp-scripts.md',
            $compiler->getAppliedOverrides()
        );
    }

    public function testSectionHeadingDerivedFromDirectoryName(): void
    {
        $dir = $this->magentoRoot . '/.bricklayer/guidelines/erp-integration';
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/sync-queue.md', 'Queue sync rules.');

        $compiler = new GuidelinesCompiler($this->magentoRoot, $this->packageRoot);
        $output = $compiler->compile('claude-code');

        $this->assertStringContainsString('## Erp Integration', $output);
        $this->assertStringContainsString('### Sync Queue', $output);
    }

    public function testOverrideFileIsTrackedButNotAddedAsNewSection(): void
    {
        // Local file at the same relative path as a bundled file → override, not addition.
        $dir = $this->magentoRoot . '/.bricklayer/guidelines/patterns';
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/plugin.md', '# Custom Plugin Guideline');

        $compiler = new GuidelinesCompiler($this->magentoRoot, $this->packageRoot);
        $output = $compiler->compile('claude-code');

        // The "Patterns" group should NOT be emitted as an additions section
        // because the file is an override (the bundled path matches).
        $this->assertStringNotContainsString("\n## Patterns\n", $output);

        $applied = $compiler->getAppliedOverrides();
        $this->assertContains('.bricklayer/guidelines/patterns/plugin.md', $applied);
    }

    public function testNoOutputWhenLocalGuidelinesDirectoryMissing(): void
    {
        $compiler = new GuidelinesCompiler($this->magentoRoot, $this->packageRoot);
        $output = $compiler->compile('claude-code');

        // None of our fixture section headings should appear.
        $this->assertStringNotContainsString('### Csp Scripts', $output);
        $this->assertStringNotContainsString('### Sync Queue', $output);
    }

    public function testMultipleFilesInSameCategoryAreGrouped(): void
    {
        $dir = $this->magentoRoot . '/.bricklayer/guidelines/project';
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/a-first.md', 'Alpha.');
        file_put_contents($dir . '/b-second.md', 'Beta.');

        $compiler = new GuidelinesCompiler($this->magentoRoot, $this->packageRoot);
        $output = $compiler->compile('claude-code');

        // Both sub-headings should appear under a single Project heading.
        $projectPos = strpos($output, '## Project');
        $this->assertNotFalse($projectPos);
        $alphaPos = strpos($output, '### A First');
        $betaPos = strpos($output, '### B Second');
        $this->assertNotFalse($alphaPos);
        $this->assertNotFalse($betaPos);
        $this->assertGreaterThan($projectPos, $alphaPos);
        $this->assertGreaterThan($projectPos, $betaPos);
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
