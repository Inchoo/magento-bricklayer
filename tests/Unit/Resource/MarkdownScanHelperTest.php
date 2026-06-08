<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Resource;

use Inchoo\MagentoBricklayer\Mcp\Resource\FileLoaderTrait;
use PHPUnit\Framework\TestCase;

class MarkdownScanHelperTest extends TestCase
{
    private string $tempDir = '';

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bricklayer_md_scan_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    /**
     * it collects markdown relative paths through one helper
     */
    public function testItCollectsMarkdownRelativePathsThroughOneHelper(): void
    {
        // Create some .md and non-.md files
        file_put_contents($this->tempDir . '/foo.md', '# Foo');
        file_put_contents($this->tempDir . '/bar.txt', 'not md');
        mkdir($this->tempDir . '/sub', 0755, true);
        file_put_contents($this->tempDir . '/sub/baz.md', '# Baz');
        file_put_contents($this->tempDir . '/sub/qux.php', '<?php');

        $subject = new class {
            use FileLoaderTrait;

            /** @return list<string> */
            public function scan(string $dir): array
            {
                return $this->collectMarkdownRelativePaths($dir);
            }
        };

        $paths = $subject->scan($this->tempDir);
        sort($paths);

        $this->assertCount(2, $paths);
        $this->assertContains('foo.md', $paths);
        $this->assertContains('sub/baz.md', $paths);
    }

    public function testItReturnsEmptyArrayForNonExistentDirectory(): void
    {
        $subject = new class {
            use FileLoaderTrait;

            /** @return list<string> */
            public function scan(string $dir): array
            {
                return $this->collectMarkdownRelativePaths($dir);
            }
        };

        $paths = $subject->scan('/this/path/does/not/exist/ever');
        $this->assertSame([], $paths);
    }

    public function testGuidelinesResourceIndexUsesCollectMarkdownRelativePaths(): void
    {
        // Create a temp guidelines directory with some .md files
        $guidelinesDir = $this->tempDir . '/guidelines';
        mkdir($guidelinesDir . '/patterns', 0755, true);
        file_put_contents($guidelinesDir . '/patterns/plugin.md', "# Plugin\n\nContent.");

        $subject = new class ($guidelinesDir) extends \Inchoo\MagentoBricklayer\Mcp\Resource\GuidelinesResource {
            public function __construct(private readonly string $guidelinesDir)
            {
            }

            protected function getGuidelinesDir(): string
            {
                return $this->guidelinesDir;
            }
        };

        $output = $subject->getGuidelinesIndex();

        $this->assertStringContainsString('Plugin', $output);
        $this->assertStringContainsString('magento://guidelines/patterns/plugin', $output);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff((array) scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
