<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use Inchoo\MagentoBricklayer\Mcp\Tool\DiagnosticTools;
use PHPUnit\Framework\TestCase;

class DiagnosticToolsTest extends TestCase
{
    /**
     * Invoke a private method on DiagnosticTools via reflection.
     *
     * DiagnosticTools::__construct() instantiates heavy dependencies (LogTools, etc.),
     * so we use newInstanceWithoutConstructor() for tests that only exercise private helpers.
     */
    private function invokePrivate(string $method, array $args): mixed
    {
        $ref = new \ReflectionClass(DiagnosticTools::class);
        $m = $ref->getMethod($method);

        $instance = $ref->newInstanceWithoutConstructor();
        return $m->invoke($instance, ...$args);
    }

    // ── resolveModuleName ──────────────────────────────────────────────

    public function testResolveModuleName_FromNamespace(): void
    {
        $result = $this->invokePrivate('resolveModuleName', [
            ['class' => 'Vendor\\Module\\Model\\Something'],
        ]);

        $this->assertSame('Vendor_Module', $result);
    }

    public function testResolveModuleName_FromAppCodePath(): void
    {
        $result = $this->invokePrivate('resolveModuleName', [
            ['class' => '', 'file' => '/var/www/html/app/code/Acme/Payment/Model/Processor.php'],
        ]);

        $this->assertSame('Acme_Payment', $result);
    }

    public function testResolveModuleName_FromStackTrace(): void
    {
        $result = $this->invokePrivate('resolveModuleName', [
            [
                'class' => '',
                'file' => '',
                'stack_trace' => [
                    ['file' => '/var/www/html/app/code/Custom/Shipping/Plugin/Rate.php'],
                ],
            ],
        ]);

        $this->assertSame('Custom_Shipping', $result);
    }

    public function testResolveModuleName_FromVendorPath(): void
    {
        $result = $this->invokePrivate('resolveModuleName', [
            ['class' => '', 'file' => '/var/www/html/vendor/magento/module-catalog/Model/Product.php'],
        ]);

        $this->assertSame('Magento_Catalog', $result);
    }

    public function testResolveModuleName_ReturnsNullForFrameworkClasses(): void
    {
        $result = $this->invokePrivate('resolveModuleName', [
            ['class' => 'PDOException', 'file' => '/usr/share/php/PDO.php'],
        ]);

        $this->assertNull($result);
    }

    // ── extractClassFromError ──────────────────────────────────────────

    public function testExtractClassFromError_FromMessage(): void
    {
        $result = $this->invokePrivate('extractClassFromError', [
            ['message' => 'Class Vendor\\Module\\Block\\Missing not found'],
        ]);

        $this->assertSame('Vendor\\Module\\Block\\Missing', $result);
    }

    public function testExtractClassFromError_FallsBackToClass(): void
    {
        $result = $this->invokePrivate('extractClassFromError', [
            ['message' => 'Something went wrong', 'class' => 'RuntimeException'],
        ]);

        $this->assertSame('RuntimeException', $result);
    }

    public function testExtractClassFromError_ReturnsNullWhenNoMatch(): void
    {
        $result = $this->invokePrivate('extractClassFromError', [
            ['message' => 'simple error message'],
        ]);

        $this->assertNull($result);
    }

    // ── findModule ─────────────────────────────────────────────────────

    public function testFindModule_FoundByName(): void
    {
        $moduleList = [
            'modules' => [
                ['name' => 'Magento_Catalog', 'version' => '104.0.0', 'enabled' => true],
                ['name' => 'Vendor_Custom', 'version' => '1.0.0', 'enabled' => true],
            ],
        ];

        $result = $this->invokePrivate('findModule', [$moduleList, 'Vendor_Custom']);

        $this->assertNotNull($result);
        $this->assertSame('Vendor_Custom', $result['name']);
        $this->assertSame('1.0.0', $result['version']);
    }

    public function testFindModule_ReturnsNullWhenNotFound(): void
    {
        $moduleList = [
            'modules' => [
                ['name' => 'Magento_Catalog', 'version' => '104.0.0', 'enabled' => true],
            ],
        ];

        $result = $this->invokePrivate('findModule', [$moduleList, 'Vendor_Missing']);

        $this->assertNull($result);
    }

    public function testFindModule_ReturnsNullForEmptyList(): void
    {
        $result = $this->invokePrivate('findModule', [[], 'Vendor_Module']);

        $this->assertNull($result);
    }

    // ── extractDisabledCaches ──────────────────────────────────────────

    public function testExtractDisabledCaches_FiltersDisabled(): void
    {
        $cacheResult = [
            'types' => [
                ['id' => 'config', 'status' => 'enabled'],
                ['id' => 'layout', 'status' => 'disabled'],
                ['id' => 'full_page', 'status' => 'disabled'],
                ['id' => 'block_html', 'status' => 'enabled'],
            ],
        ];

        $result = $this->invokePrivate('extractDisabledCaches', [$cacheResult]);

        $this->assertSame(['layout', 'full_page'], $result);
    }

    public function testExtractDisabledCaches_ReturnsEmptyWhenAllEnabled(): void
    {
        $cacheResult = [
            'types' => [
                ['id' => 'config', 'status' => 'enabled'],
                ['id' => 'layout', 'status' => 'enabled'],
            ],
        ];

        $result = $this->invokePrivate('extractDisabledCaches', [$cacheResult]);

        $this->assertSame([], $result);
    }

    public function testExtractDisabledCaches_HandlesEmptyInput(): void
    {
        $result = $this->invokePrivate('extractDisabledCaches', [[]]);

        $this->assertSame([], $result);
    }

    // ── extractInvalidIndexers ─────────────────────────────────────────

    public function testExtractInvalidIndexers_FiltersInvalid(): void
    {
        $indexerResult = [
            'indexers' => [
                ['indexer_id' => 'catalog_product_price', 'status' => 'invalid'],
                ['indexer_id' => 'catalogsearch_fulltext', 'status' => 'valid'],
                ['indexer_id' => 'catalog_category_product', 'status' => 'invalid'],
            ],
        ];

        $result = $this->invokePrivate('extractInvalidIndexers', [$indexerResult]);

        $this->assertSame(['catalog_product_price', 'catalog_category_product'], $result);
    }

    public function testExtractInvalidIndexers_ReturnsEmptyWhenAllValid(): void
    {
        $indexerResult = [
            'indexers' => [
                ['indexer_id' => 'catalog_product_price', 'status' => 'valid'],
                ['indexer_id' => 'catalogsearch_fulltext', 'status' => 'valid'],
            ],
        ];

        $result = $this->invokePrivate('extractInvalidIndexers', [$indexerResult]);

        $this->assertSame([], $result);
    }

    // ── sinceToHours ───────────────────────────────────────────────────

    public function testSinceToHours_Minutes(): void
    {
        $this->assertSame(1, $this->invokePrivate('sinceToHours', ['5m']));
        $this->assertSame(1, $this->invokePrivate('sinceToHours', ['59m']));
        $this->assertSame(1, $this->invokePrivate('sinceToHours', ['60m']));
        $this->assertSame(2, $this->invokePrivate('sinceToHours', ['61m']));
    }

    public function testSinceToHours_Hours(): void
    {
        $this->assertSame(1, $this->invokePrivate('sinceToHours', ['1h']));
        $this->assertSame(2, $this->invokePrivate('sinceToHours', ['2h']));
        $this->assertSame(24, $this->invokePrivate('sinceToHours', ['24h']));
    }

    public function testSinceToHours_Days(): void
    {
        $this->assertSame(24, $this->invokePrivate('sinceToHours', ['1d']));
        $this->assertSame(72, $this->invokePrivate('sinceToHours', ['3d']));
        $this->assertSame(168, $this->invokePrivate('sinceToHours', ['7d']));
    }

    public function testSinceToHours_InvalidInput(): void
    {
        $this->assertSame(1, $this->invokePrivate('sinceToHours', ['invalid']));
        $this->assertSame(1, $this->invokePrivate('sinceToHours', ['']));
        $this->assertSame(1, $this->invokePrivate('sinceToHours', ['5x']));
    }

    // ── countMatchingErrors ────────────────────────────────────────────

    public function testCountMatchingErrors_FoundInMap(): void
    {
        $analysis = [
            'error_types' => [
                'Magento\\Framework\\Exception\\LocalizedException' => 15,
                'TypeError' => 3,
            ],
        ];
        $error = ['class' => 'TypeError'];

        $result = $this->invokePrivate('countMatchingErrors', [$analysis, $error]);

        $this->assertSame(3, $result);
    }

    public function testCountMatchingErrors_NotFoundInMap(): void
    {
        $analysis = [
            'error_types' => ['TypeError' => 3],
        ];
        $error = ['class' => 'ValueError'];

        $result = $this->invokePrivate('countMatchingErrors', [$analysis, $error]);

        $this->assertSame(0, $result);
    }

    public function testCountMatchingErrors_EmptyClass(): void
    {
        $analysis = ['error_types' => ['TypeError' => 3]];
        $error = ['class' => ''];

        $result = $this->invokePrivate('countMatchingErrors', [$analysis, $error]);

        $this->assertSame(0, $result);
    }

    public function testCountMatchingErrors_NoClass(): void
    {
        $analysis = ['error_types' => ['TypeError' => 3]];
        $error = [];

        $result = $this->invokePrivate('countMatchingErrors', [$analysis, $error]);

        $this->assertSame(0, $result);
    }

    // ── matchPattern ───────────────────────────────────────────────────

    public function testMatchPattern_ClassNotFound(): void
    {
        $error = ['message' => 'Class Vendor\\Module\\Block\\Missing not found'];

        $result = $this->invokePrivate('matchPattern', [$error]);

        $this->assertNotNull($result);
        $this->assertSame('autoload', $result['category']);
    }

    public function testMatchPattern_InvalidBlockType(): void
    {
        $error = ['message' => 'Invalid block type: Vendor\\Module\\Block\\Custom'];

        $result = $this->invokePrivate('matchPattern', [$error]);

        $this->assertNotNull($result);
        $this->assertSame('layout', $result['category']);
    }

    public function testMatchPattern_AreaCodeNotSet(): void
    {
        $error = ['message' => 'Area code is not set'];

        $result = $this->invokePrivate('matchPattern', [$error]);

        $this->assertNotNull($result);
        $this->assertSame('area', $result['category']);
    }

    public function testMatchPattern_SqlState(): void
    {
        $error = ['message' => "SQLSTATE[42S02]: Base table or view not found"];

        $result = $this->invokePrivate('matchPattern', [$error]);

        $this->assertNotNull($result);
        $this->assertSame('database', $result['category']);
    }

    public function testMatchPattern_MemoryExhausted(): void
    {
        $error = ['message' => 'Allowed memory size of 134217728 bytes exhausted'];

        $result = $this->invokePrivate('matchPattern', [$error]);

        $this->assertNotNull($result);
        $this->assertSame('memory', $result['category']);
    }

    public function testMatchPattern_NoMatch(): void
    {
        $error = ['message' => 'Something completely unrelated happened'];

        $result = $this->invokePrivate('matchPattern', [$error]);

        $this->assertNull($result);
    }

    public function testMatchPattern_CompilationError(): void
    {
        $error = ['message' => 'Compilation error during DI compilation'];

        $result = $this->invokePrivate('matchPattern', [$error]);

        $this->assertNotNull($result);
        $this->assertSame('di', $result['category']);
    }

    public function testMatchPattern_SearchEngineError(): void
    {
        $error = ['message' => 'No alive nodes found in cluster'];

        $result = $this->invokePrivate('matchPattern', [$error]);

        $this->assertNotNull($result);
        $this->assertSame('search', $result['category']);
    }

    public function testMatchPattern_MatchesClassField(): void
    {
        // The matchPattern method also checks the 'class' field
        $error = ['message' => 'Something happened', 'class' => 'TypeError'];

        $result = $this->invokePrivate('matchPattern', [$error]);

        $this->assertNotNull($result);
        $this->assertSame('type', $result['category']);
    }

    // ── buildSuggestions ───────────────────────────────────────────────

    public function testBuildSuggestions_CacheAlreadyDisabled(): void
    {
        $matched = [
            'category' => 'layout',
            'relevant_caches' => ['layout', 'full_page'],
            'suggestions' => [
                [
                    'action' => 'Flush layout and block caches',
                    'reason' => 'Stale layout cache',
                    'command' => 'bin/magento cache:clean layout full_page',
                    'confidence' => 'high',
                ],
            ],
        ];
        $env = [
            'cache_disabled' => ['layout', 'full_page'],
            'indexers_invalid' => [],
        ];

        $result = $this->invokePrivate('buildSuggestions', [$matched, $env, null, null]);

        $this->assertNotEmpty($result);
        $cacheSuggestion = $result[0];
        $this->assertSame('low', $cacheSuggestion['confidence']);
        $this->assertStringContainsString('already disabled', $cacheSuggestion['note']);
    }

    public function testBuildSuggestions_DiCompileRecentlyRun(): void
    {
        $matched = [
            'category' => 'di',
            'relevant_caches' => [],
            'suggestions' => [
                [
                    'action' => 'Clear generated code and recompile DI',
                    'reason' => 'DI compilation errors',
                    'command' => 'rm -rf generated/code generated/metadata && bin/magento setup:di:compile',
                    'confidence' => 'high',
                ],
            ],
        ];
        $env = [
            'cache_disabled' => [],
            'indexers_invalid' => [],
            'generated_code_age' => date('Y-m-d H:i:s', time() - 30), // 30 seconds ago
        ];

        $result = $this->invokePrivate('buildSuggestions', [$matched, $env, null, null]);

        $this->assertNotEmpty($result);
        $diSuggestion = $result[0];
        $this->assertSame('low', $diSuggestion['confidence']);
        $this->assertStringContainsString('compiled less than 2 minutes ago', $diSuggestion['note']);
    }

    public function testBuildSuggestions_ModuleNotFound(): void
    {
        $env = ['cache_disabled' => [], 'indexers_invalid' => []];
        $module = ['module_name' => 'Vendor_Missing', 'found' => false];

        $result = $this->invokePrivate('buildSuggestions', [null, $env, $module, null]);

        $this->assertNotEmpty($result);
        $this->assertSame('high', $result[0]['confidence']);
        $this->assertStringContainsString('Vendor_Missing', $result[0]['action']);
        $this->assertStringContainsString('not found', $result[0]['action']);
    }

    public function testBuildSuggestions_ModuleDisabled(): void
    {
        $env = ['cache_disabled' => [], 'indexers_invalid' => []];
        $module = ['module_name' => 'Vendor_Disabled', 'found' => true, 'enabled' => false];

        $result = $this->invokePrivate('buildSuggestions', [null, $env, $module, null]);

        $this->assertNotEmpty($result);
        $this->assertSame('high', $result[0]['confidence']);
        $this->assertStringContainsString('module:enable', $result[0]['command']);
        $this->assertStringContainsString('Vendor_Disabled', $result[0]['command']);
    }

    public function testBuildSuggestions_ClassMissing(): void
    {
        $env = ['cache_disabled' => [], 'indexers_invalid' => []];
        $di = ['class' => 'Vendor\\Module\\Model\\Missing', 'class_exists' => false];

        $result = $this->invokePrivate('buildSuggestions', [null, $env, null, $di]);

        $this->assertNotEmpty($result);
        $this->assertSame('high', $result[0]['confidence']);
        $this->assertStringContainsString('does not exist', $result[0]['action']);
        $this->assertStringContainsString('dump-autoload', $result[0]['command']);
        $this->assertStringContainsString('di:compile', $result[0]['command']);
    }

    public function testBuildSuggestions_InvalidIndexersRelevant(): void
    {
        $matched = [
            'category' => 'catalog',
            'relevant_caches' => [],
            'suggestions' => [
                [
                    'action' => 'Some catalog action',
                    'reason' => 'Some reason',
                    'command' => null,
                    'confidence' => 'medium',
                ],
            ],
        ];
        $env = [
            'cache_disabled' => [],
            'indexers_invalid' => ['catalog_product_price', 'catalog_category_product'],
        ];

        $result = $this->invokePrivate('buildSuggestions', [$matched, $env, null, null]);

        // Last suggestion should be about reindexing
        $lastSuggestion = end($result);
        $this->assertStringContainsString('Reindex', $lastSuggestion['action']);
        $this->assertStringContainsString('catalog_product_price', $lastSuggestion['command']);
        $this->assertStringContainsString('catalog_category_product', $lastSuggestion['command']);
    }

    public function testBuildSuggestions_NoPatternNoContext(): void
    {
        $env = ['cache_disabled' => [], 'indexers_invalid' => []];

        $result = $this->invokePrivate('buildSuggestions', [null, $env, null, null]);

        $this->assertCount(1, $result);
        $this->assertSame('low', $result[0]['confidence']);
        $this->assertStringContainsString('stack trace', $result[0]['action']);
    }
}
