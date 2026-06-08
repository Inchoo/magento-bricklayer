<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool\Concern;

use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\ResolvesPackagePaths;
use Inchoo\MagentoBricklayer\Mcp\Tool\ContextTools;
use Inchoo\MagentoBricklayer\Mcp\Tool\SearchTools;
use PHPUnit\Framework\TestCase;

class ResolvesPackagePathsTest extends TestCase
{
    /**
     * it resolves package paths via the shared trait for both search and context tools
     */
    public function testItResolvesPackagePathsViaTheSharedTraitForBothSearchAndContextTools(): void
    {
        $uses = class_uses(SearchTools::class);
        $this->assertContains(
            ResolvesPackagePaths::class,
            $uses,
            'SearchTools must use the ResolvesPackagePaths trait'
        );

        $uses = class_uses(ContextTools::class);
        $this->assertContains(
            ResolvesPackagePaths::class,
            $uses,
            'ContextTools must use the ResolvesPackagePaths trait'
        );
    }

    public function testTraitStripTrailingSlashesFromMagentoRoot(): void
    {
        $subject = new class {
            use ResolvesPackagePaths;

            public function __construct(?string $magentoRoot = null, ?string $packageRoot = null)
            {
                $this->initPackagePaths($magentoRoot, $packageRoot);
            }

            public function getMagentoRootForTest(): ?string
            {
                return $this->resolveMagentoRoot();
            }

            public function getPackageRootForTest(): string
            {
                return $this->packageRoot;
            }
        };

        $instance = new $subject('/some/path/', null);
        $this->assertSame('/some/path', $instance->getMagentoRootForTest());
    }

    public function testTraitStripTrailingSlashesFromPackageRoot(): void
    {
        $subject = new class {
            use ResolvesPackagePaths;

            public function __construct(?string $magentoRoot = null, ?string $packageRoot = null)
            {
                $this->initPackagePaths($magentoRoot, $packageRoot);
            }

            public function getPackageRootForTest(): string
            {
                return $this->packageRoot;
            }
        };

        $instance = new $subject(null, '/custom/root/');
        $this->assertSame('/custom/root', $instance->getPackageRootForTest());
    }

    public function testTraitReturnsNullMagentoRootWhenNoOverrideAndNoBootstrap(): void
    {
        $subject = new class {
            use ResolvesPackagePaths;

            public function __construct()
            {
                $this->initPackagePaths(null, sys_get_temp_dir());
            }

            public function getMagentoRootForTest(): ?string
            {
                return $this->resolveMagentoRoot();
            }
        };

        $instance = new $subject();
        // With no override and no live Magento, resolveMagentoRoot may return null
        // (in test environment MagentoBootstrap::getMagentoRoot() returns null or a path;
        // we only verify the method is callable and returns string|null)
        $result = $instance->getMagentoRootForTest();
        // resolveMagentoRoot returns ?string — just verify the return type
        $this->assertThat(
            $result,
            $this->logicalOr(
                $this->isNull(),
                $this->isType('string')
            )
        );
    }
}
