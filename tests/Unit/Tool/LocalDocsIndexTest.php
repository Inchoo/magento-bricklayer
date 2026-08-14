<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use Inchoo\MagentoBricklayer\Guidelines\LocalOverrideHelper;
use Inchoo\MagentoBricklayer\Mcp\Tool\SearchTools;
use PHPUnit\Framework\TestCase;

class LocalDocsIndexTest extends TestCase
{
    private string $magentoRoot;
    private string $packageRoot;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/bricklayer_docs_index_' . uniqid();
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

    public function testLocalSkillIsDiscoverableWithProjectPrefix(): void
    {
        $dir = $this->magentoRoot . '/.bricklayer/skills/csp-scripts';
        mkdir($dir, 0755, true);
        file_put_contents(
            $dir . '/SKILL.md',
            "---\nname: CSP Scripts\ndescription: Manage Content Security Policy scripts in Magento.\n---\n\n# CSP Scripts"
        );

        $tools = new SearchTools($this->magentoRoot, $this->packageRoot);
        $result = $tools->searchDocs('csp scripts');

        $this->assertGreaterThan(0, $result['result_count']);
        $found = false;
        foreach ($result['results'] as $entry) {
            if (str_starts_with($entry['category'], '[Project]') && str_contains($entry['category'], 'CSP Scripts')) {
                $found = true;
                $this->assertSame('local', $entry['source']);
                break;
            }
        }
        $this->assertTrue($found, 'Expected local csp-scripts entry with [Project] prefix');
    }

    public function testLocalGuidelineIsDiscoverable(): void
    {
        $dir = $this->magentoRoot . '/.bricklayer/guidelines/project';
        mkdir($dir, 0755, true);
        file_put_contents(
            $dir . '/csp-scripts.md',
            "Project CSP conventions for inline scripts and nonces."
        );

        $tools = new SearchTools($this->magentoRoot, $this->packageRoot);
        $result = $tools->searchDocs('csp');

        $found = false;
        foreach ($result['results'] as $entry) {
            if (str_starts_with($entry['category'], '[Project]')) {
                $this->assertSame('local', $entry['source']);
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected local guideline entry with [Project] prefix');
    }

    public function testOverrideSkillReplacesBundledEntryNoDuplicate(): void
    {
        $dir = $this->magentoRoot . '/.bricklayer/skills/plugin';
        mkdir($dir, 0755, true);
        file_put_contents(
            $dir . '/SKILL.md',
            "---\nname: Local Plugin\ndescription: Project plugin conventions and patterns.\n---\n\n# Local"
        );

        $tools = new SearchTools($this->magentoRoot, $this->packageRoot);
        $result = $tools->searchDocs('plugin');

        $pluginResults = array_filter(
            $result['results'],
            fn($r) => str_contains(strtolower($r['category']), 'plugin')
                && !str_contains($r['category'], 'plugin-list')
        );
        // At most one entry for the plugin category (local replaced bundled)
        $this->assertLessThanOrEqual(1, count(array_filter(
            $pluginResults,
            fn($r) => $r['category'] === 'plugin' || str_contains($r['category'], '[Project]')
        )));
    }

    public function testBundledEntryHasNoProjectPrefix(): void
    {
        $tools = new SearchTools($this->magentoRoot, $this->packageRoot);
        $result = $tools->searchDocs('plugin');

        $bundled = null;
        foreach ($result['results'] as $entry) {
            if ($entry['category'] === 'plugin') {
                $bundled = $entry;
                break;
            }
        }

        $this->assertNotNull($bundled);
        $this->assertArrayNotHasKey('source', $bundled);
    }

    public function testLocalEntryCountReflectsLocalAdditions(): void
    {
        $dir = $this->magentoRoot . '/.bricklayer/skills/csp-scripts';
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/SKILL.md', "---\nname: CSP\ndescription: CSP.\n---\n\nBody");

        $guidelineDir = $this->magentoRoot . '/.bricklayer/guidelines/project';
        mkdir($guidelineDir, 0755, true);
        file_put_contents($guidelineDir . '/erp.md', 'ERP guide');

        $tools = new SearchTools($this->magentoRoot, $this->packageRoot);
        $this->assertSame(2, $tools->getLocalDocumentationEntryCount());
    }

    public function testStripFrontmatterRemovesYamlBlock(): void
    {
        $content = "---\nname: X\ndescription: Y\n---\n\n# Body";
        $this->assertSame('# Body', LocalOverrideHelper::stripFrontmatter($content));
    }

    public function testStripFrontmatterLeavesContentWithoutBlockUnchanged(): void
    {
        $content = "# Title\n\nNo frontmatter here.";
        $this->assertSame($content, LocalOverrideHelper::stripFrontmatter($content));
    }

    public function testParseSkillFrontmatterExtractsNameAndDescription(): void
    {
        $path = $this->magentoRoot . '/.bricklayer/skills/example/SKILL.md';
        mkdir(dirname($path), 0755, true);
        file_put_contents(
            $path,
            "---\nname: Example Name\ndescription: Example description here.\n---\n\nBody"
        );

        $meta = LocalOverrideHelper::parseSkillFrontmatter($path);
        $this->assertSame('Example Name', $meta['name']);
        $this->assertSame('Example description here.', $meta['description']);
    }

    public function testParseSkillFrontmatterReturnsNullsForFileWithoutFrontmatter(): void
    {
        $path = $this->magentoRoot . '/.bricklayer/skills/example/SKILL.md';
        mkdir(dirname($path), 0755, true);
        file_put_contents($path, "# No frontmatter\n\nJust content.");

        $meta = LocalOverrideHelper::parseSkillFrontmatter($path);
        $this->assertNull($meta['name']);
        $this->assertNull($meta['description']);
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
