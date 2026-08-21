<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Integration;

use Composer\Autoload\ClassLoader;
use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that run against a real, installed Magento.
 *
 * The installation is selected via BRICKLAYER_MAGENTO_ROOT. When the
 * variable is missing the whole class is skipped (deliberate local run
 * without an environment); when it is set but the bootstrap fails, the
 * suite fails loudly instead of green-skipping a broken environment.
 */
abstract class IntegrationTestCase extends TestCase
{
    private static bool $bootstrapped = false;

    public static function setUpBeforeClass(): void
    {
        $root = getenv('BRICKLAYER_MAGENTO_ROOT');

        if (!is_string($root) || $root === '') {
            self::markTestSkipped(
                'BRICKLAYER_MAGENTO_ROOT is not set; integration tests need a bootable Magento installation.'
            );
        }

        if (self::$bootstrapped) {
            return;
        }

        $loaders = ClassLoader::getRegisteredLoaders();

        try {
            MagentoBootstrap::initialize($root);
        } catch (\Throwable $e) {
            self::fail(sprintf('Unable to bootstrap Magento at "%s": %s', $root, $e->getMessage()));
        }

        // Magento's autoloader registers itself in front of ours. Put the
        // loaders that were active before the bootstrap back in front, so
        // packages present in both vendor trees (PHPUnit above all: Magento
        // ships its own major in require-dev) keep resolving from the tree
        // they were first loaded from instead of mixing majors mid-run.
        foreach ($loaders as $loader) {
            $loader->unregister();
            $loader->register(true);
        }

        self::$bootstrapped = true;
    }

    protected static function magentoRoot(): string
    {
        $root = getenv('BRICKLAYER_MAGENTO_ROOT');

        if (!is_string($root) || $root === '') {
            self::fail('BRICKLAYER_MAGENTO_ROOT is not set.');
        }

        return $root;
    }

    /**
     * Assert a tool response is not the uniform error shape.
     *
     * @param array<mixed> $response
     */
    protected static function assertToolSuccess(array $response): void
    {
        self::assertNotSame(
            true,
            $response['error'] ?? null,
            'Tool returned an error: ' . substr(var_export($response, true), 0, 2000)
        );
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    protected static function arrayValue(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (!is_array($value)) {
            self::fail(sprintf(
                'Expected key "%s" to hold an array, got %s in: %s',
                $key,
                get_debug_type($value),
                substr(var_export($data, true), 0, 2000)
            ));
        }

        return $value;
    }

    /**
     * @param array<mixed> $data
     */
    protected static function stringValue(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (!is_string($value)) {
            self::fail(sprintf(
                'Expected key "%s" to hold a string, got %s in: %s',
                $key,
                get_debug_type($value),
                substr(var_export($data, true), 0, 2000)
            ));
        }

        return $value;
    }
}
