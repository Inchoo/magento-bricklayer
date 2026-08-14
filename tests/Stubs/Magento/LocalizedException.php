<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 *
 * Minimal stand-in for Magento's LocalizedException, loaded by
 * tests/bootstrap.php only when the real class is unavailable.
 */

declare(strict_types=1);

namespace Magento\Framework\Exception;

use Magento\Framework\Phrase;

class LocalizedException extends \Exception
{
    public function __construct(
        private readonly Phrase $phrase,
        ?\Exception $cause = null,
        int $code = 0
    ) {
        parent::__construct($phrase->render(), $code, $cause);
    }

    public function getRawMessage(): string
    {
        return $this->phrase->render();
    }
}
