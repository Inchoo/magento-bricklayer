<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 *
 * Minimal stand-in for Magento's ProductInterface, loaded by
 * tests/bootstrap.php only when the real interface is unavailable.
 * Declares only the methods unit tests configure on mocks of it.
 */

declare(strict_types=1);

namespace Magento\Catalog\Api\Data;

interface ProductInterface
{
    /**
     * @return string|null
     */
    public function getTypeId();

    /**
     * @return string|null
     */
    public function getSku();
}
