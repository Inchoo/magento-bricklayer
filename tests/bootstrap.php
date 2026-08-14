<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

$candidates = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
    __DIR__ . '/../../../../vendor/autoload.php',
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

$stubs = [
    \Magento\Framework\Component\ComponentRegistrar::class => __DIR__ . '/Stubs/Magento/ComponentRegistrar.php',
    \Magento\Framework\Phrase::class => __DIR__ . '/Stubs/Magento/Phrase.php',
    \Magento\Framework\Exception\LocalizedException::class => __DIR__ . '/Stubs/Magento/LocalizedException.php',
    \Magento\Framework\App\State::class => __DIR__ . '/Stubs/Magento/State.php',
    \Magento\Framework\DataObject::class => __DIR__ . '/Stubs/Magento/DataObject.php',
];

foreach ($stubs as $class => $file) {
    if (!class_exists($class)) {
        require_once $file;
    }
}

$stubInterfaces = [
    \Magento\Catalog\Api\Data\ProductInterface::class => __DIR__ . '/Stubs/Magento/ProductInterface.php',
    \Magento\Customer\Api\Data\GroupInterface::class => __DIR__ . '/Stubs/Magento/GroupInterface.php',
];

foreach ($stubInterfaces as $interface => $file) {
    if (!interface_exists($interface)) {
        require_once $file;
    }
}
