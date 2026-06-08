<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Config;

use Inchoo\MagentoBricklayer\Config\ConfigValidator;
use PHPUnit\Framework\TestCase;

class ConfigValidatorTest extends TestCase
{
    private ConfigValidator $validator;

    protected function setUp(): void
    {
        ConfigValidator::clearKnownToolsCache();
        $this->validator = new ConfigValidator();
    }

    protected function tearDown(): void
    {
        ConfigValidator::clearKnownToolsCache();
    }

    public function testValidateReturnsTrueForEmptyConfig(): void
    {
        $result = $this->validator->validate([]);
        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testValidateReturnsTrueForValidConfig(): void
    {
        $config = [
            'tools' => [
                'application-info' => [
                    'enabled' => true,
                ],
                'database-query' => [
                    'enabled' => true,
                    'max_rows' => 100,
                ],
            ],
            'guidelines' => [
                'include' => ['core', 'modules'],
                'exclude' => [],
            ],
            'agents' => ['claude-code', 'cursor'],
        ];

        $result = $this->validator->validate($config);
        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testValidateReturnsErrorForInvalidToolsType(): void
    {
        $config = [
            'tools' => 'invalid',
        ];

        $result = $this->validator->validate($config);
        $this->assertFalse($result);
        $this->assertNotEmpty($this->validator->getErrors());
    }

    public function testValidateReturnsErrorForInvalidToolEnabled(): void
    {
        $config = [
            'tools' => [
                'application-info' => [
                    'enabled' => 'yes', // Should be boolean
                ],
            ],
        ];

        $result = $this->validator->validate($config);
        $this->assertFalse($result);
        $this->assertContains(
            "Tool 'application-info' 'enabled' option must be a boolean",
            $this->validator->getErrors()
        );
    }

    public function testValidateReturnsErrorForInvalidMaxRows(): void
    {
        $config = [
            'tools' => [
                'database-query' => [
                    'max_rows' => -1,
                ],
            ],
        ];

        $result = $this->validator->validate($config);
        $this->assertFalse($result);
    }

    public function testValidateReturnsWarningForUnknownTool(): void
    {
        $config = [
            'tools' => [
                'unknown-tool' => [
                    'enabled' => true,
                ],
            ],
        ];

        $this->validator->validate($config);
        $this->assertContains(
            "Unknown tool: 'unknown-tool'",
            $this->validator->getWarnings()
        );
    }

    public function testValidateReturnsWarningForUnknownAgent(): void
    {
        $config = [
            'agents' => ['unknown-agent'],
        ];

        $this->validator->validate($config);
        $this->assertContains(
            "Unknown agent: 'unknown-agent'",
            $this->validator->getWarnings()
        );
    }

    public function testValidateReturnsErrorForInvalidGuidelinesInclude(): void
    {
        $config = [
            'guidelines' => [
                'include' => 'not-an-array',
            ],
        ];

        $result = $this->validator->validate($config);
        $this->assertFalse($result);
    }

    public function testGetKnownToolsReturnsArray(): void
    {
        $tools = ConfigValidator::getKnownTools();
        $this->assertIsArray($tools);
        $this->assertContains('application-info', $tools);
        $this->assertContains('product-get', $tools);
    }

    public function testGetKnownAgentsReturnsArray(): void
    {
        $agents = ConfigValidator::getKnownAgents();
        $this->assertIsArray($agents);
        $this->assertContains('claude-code', $agents);
        $this->assertContains('cursor', $agents);
    }
}
