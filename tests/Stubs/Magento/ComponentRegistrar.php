<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 *
 * Minimal stand-in for Magento's ComponentRegistrar, loaded by
 * tests/bootstrap.php only when the real class is unavailable
 * (standalone clone / CI without a surrounding Magento project).
 * Mirrors the real class's registration semantics.
 */

declare(strict_types=1);

namespace Magento\Framework\Component;

class ComponentRegistrar
{
    public const MODULE = 'module';
    public const LIBRARY = 'library';
    public const THEME = 'theme';
    public const LANGUAGE = 'language';

    /** @var array<string, array<string, string>> */
    private static array $paths = [
        self::MODULE => [],
        self::LIBRARY => [],
        self::THEME => [],
        self::LANGUAGE => [],
    ];

    public static function register(string $type, string $componentName, string $path): void
    {
        if (isset(self::$paths[$type][$componentName])) {
            if (self::$paths[$type][$componentName] !== str_replace('\\', '/', $path)) {
                throw new \LogicException($componentName . ' component already exists');
            }

            return;
        }

        self::$paths[$type][$componentName] = str_replace('\\', '/', $path);
    }

    /**
     * @return array<string, string>
     */
    public function getPaths(string $type): array
    {
        return self::$paths[$type] ?? [];
    }

    public function getPath(string $type, string $componentName): ?string
    {
        return self::$paths[$type][$componentName] ?? null;
    }
}
