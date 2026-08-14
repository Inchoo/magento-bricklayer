<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 *
 * Minimal stand-in for Magento's Phrase, loaded by tests/bootstrap.php
 * only when the real class is unavailable.
 */

declare(strict_types=1);

namespace Magento\Framework;

class Phrase
{
    /**
     * @param array<int|string, mixed> $arguments
     */
    public function __construct(
        private readonly string $text,
        private readonly array $arguments = []
    ) {
    }

    public function render(): string
    {
        return $this->arguments === [] ? $this->text : vsprintf($this->text, $this->arguments);
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
