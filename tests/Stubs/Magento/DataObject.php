<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 *
 * Minimal stand-in for Magento's DataObject, loaded by tests/bootstrap.php
 * only when the real class is unavailable. Implements the small data-bag
 * surface order-create relies on.
 */

declare(strict_types=1);

namespace Magento\Framework;

class DataObject
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private array $data = [])
    {
    }

    /**
     * @return mixed
     */
    public function getData(?string $key = null)
    {
        if ($key === null) {
            return $this->data;
        }

        return $this->data[$key] ?? null;
    }

    /**
     * @param mixed $value
     */
    public function setData(string $key, $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * @param array<int, mixed> $args
     * @return mixed
     */
    public function __call(string $method, array $args)
    {
        $key = strtolower((string) preg_replace('/(.)([A-Z])/', '$1_$2', substr($method, 3)));

        if (str_starts_with($method, 'get')) {
            return $this->data[$key] ?? null;
        }

        if (str_starts_with($method, 'set')) {
            $this->data[$key] = $args[0] ?? null;

            return $this;
        }

        return null;
    }
}
