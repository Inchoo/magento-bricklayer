<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 *
 * Minimal stand-in for Magento's customer GroupInterface, loaded by
 * tests/bootstrap.php only when the real interface is unavailable.
 * Provides the group-id constants referenced by order-create.
 */

declare(strict_types=1);

namespace Magento\Customer\Api\Data;

interface GroupInterface
{
    public const NOT_LOGGED_IN_ID = 0;
    public const CUST_GROUP_ALL = 32000;
}
