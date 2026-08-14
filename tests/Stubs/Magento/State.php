<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 *
 * Minimal stand-in for Magento's App\State, loaded by tests/bootstrap.php
 * only when the real class is unavailable. Provides the deploy-mode
 * constants so `State::MODE_*` references resolve; instances are always
 * test fakes supplied through the bootstrapped object manager.
 */

declare(strict_types=1);

namespace Magento\Framework\App;

class State
{
    public const MODE_DEVELOPER = 'developer';
    public const MODE_PRODUCTION = 'production';
    public const MODE_DEFAULT = 'default';
}
