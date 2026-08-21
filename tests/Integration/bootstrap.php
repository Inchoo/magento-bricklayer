<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

/*
 * Integration bootstrap. Unlike tests/bootstrap.php this loads NO Magento
 * stubs: the suite runs against a real installation, resolved from the
 * BRICKLAYER_MAGENTO_ROOT environment variable and bootstrapped lazily in
 * IntegrationTestCase::setUpBeforeClass() so a missing environment skips
 * the suite instead of erroring.
 */

$candidates = [
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../../autoload.php',
    __DIR__ . '/../../../../../vendor/autoload.php',
];

$autoloaderFound = false;
foreach ($candidates as $candidate) {
    if (file_exists($candidate)) {
        require_once $candidate;
        $autoloaderFound = true;
        break;
    }
}

if (!$autoloaderFound) {
    fwrite(STDERR, "Unable to locate Composer autoloader. Run 'composer install' first.\n");
    exit(1);
}
