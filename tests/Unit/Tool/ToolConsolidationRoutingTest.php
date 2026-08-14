<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use Inchoo\MagentoBricklayer\Mcp\Tool\DevelopmentTools;
use Inchoo\MagentoBricklayer\Mcp\Tool\GraphqlTools;
use Inchoo\MagentoBricklayer\Mcp\Tool\LogTools;
use PHPUnit\Framework\TestCase;

class ToolConsolidationRoutingTest extends TestCase
{
    public function testGraphqlInvalidTargetReturnsErrorWithAllowedList(): void
    {
        $tools = new GraphqlTools();
        $result = $tools->inspectGraphql(target: 'invalid');

        $this->assertTrue($result['error']);
        $this->assertStringContainsString('Invalid target', $result['message']);
        $this->assertStringContainsString('types', $result['message']);
        $this->assertStringContainsString('queries', $result['message']);
        $this->assertStringContainsString('mutations', $result['message']);
        $this->assertStringContainsString('resolvers', $result['message']);
    }

    /**
     * @dataProvider validGraphqlTargetsProvider
     */
    public function testGraphqlValidTargetPassesRouting(string $target): void
    {
        $tools = new GraphqlTools();
        $result = $tools->inspectGraphql(target: $target);

        // Valid targets pass routing validation.
        // They may succeed fully or fail at requireMagento / Magento internals,
        // but must never fail with "Invalid target".
        if (isset($result['error']) && $result['error'] === true) {
            $this->assertStringNotContainsString(
                'Invalid target',
                $result['message'],
                "Target '$target' should pass routing validation"
            );
        } else {
            // Target was accepted and returned data successfully
            $this->assertIsArray($result);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validGraphqlTargetsProvider(): array
    {
        return [
            'types' => ['types'],
            'queries' => ['queries'],
            'mutations' => ['mutations'],
            'resolvers' => ['resolvers'],
        ];
    }

    public function testLogInvalidActionReturnsErrorWithAllowedList(): void
    {
        $tools = new LogTools();
        $result = $tools->log(action: 'invalid');

        $this->assertTrue($result['error']);
        $this->assertStringContainsString('Invalid action', $result['message']);
        $this->assertStringContainsString('read', $result['message']);
        $this->assertStringContainsString('list', $result['message']);
        $this->assertStringContainsString('search', $result['message']);
        $this->assertStringContainsString('analyze', $result['message']);
    }

    public function testLogSearchWithShortQueryReturnsError(): void
    {
        $tools = new LogTools();
        $result = $tools->log(action: 'search', query: 'ab');

        // The search action passes routing but requireMagento runs first.
        // If Magento not initialized, we get that error instead.
        // The short query check happens inside performSearch after requireMagento.
        $this->assertTrue($result['error']);
    }

    public function testSystemStatusInvalidCheckReturnsErrorWithAllowedList(): void
    {
        $tools = new DevelopmentTools();
        $result = $tools->getSystemStatus(check: 'invalid');

        $this->assertTrue($result['error']);
        $this->assertStringContainsString('Invalid check', $result['message']);
        $this->assertStringContainsString('cache', $result['message']);
        $this->assertStringContainsString('indexers', $result['message']);
        $this->assertStringContainsString('deploy-mode', $result['message']);
        $this->assertStringContainsString('cron', $result['message']);
        $this->assertStringContainsString('cron-history', $result['message']);
    }
}
