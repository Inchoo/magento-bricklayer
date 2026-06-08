<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool\Concern;

use Inchoo\MagentoBricklayer\Mcp\Tool\ConfigurationTools;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\MasksSensitiveConfig;
use Inchoo\MagentoBricklayer\Mcp\Tool\DatabaseTools;
use Inchoo\MagentoBricklayer\Tests\Unit\Tool\Fixture\MasksSensitiveConfigTestSubject;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Fixture/MasksSensitiveConfigTestSubject.php';

class MasksSensitiveConfigTest extends TestCase
{
    private MasksSensitiveConfigTestSubject $subject;

    protected function setUp(): void
    {
        $this->subject = new MasksSensitiveConfigTestSubject();
    }

    public function testItIdentifiesAPaymentConfigPathAsSensitive(): void
    {
        $this->assertTrue($this->subject->callIsSensitivePath('payment/paypal_express/active'));
        $this->assertTrue($this->subject->callIsSensitivePath('payment'));
    }

    public function testItMasksTheValueOfASensitiveConfigPath(): void
    {
        $maskedValue = $this->subject->callMaskValue();
        $this->assertSame('***MASKED***', $maskedValue);
    }

    public function testItLeavesANonSensitiveConfigPathUnmasked(): void
    {
        $this->assertFalse($this->subject->callIsSensitivePath('general/store_information/name'));
        $this->assertFalse($this->subject->callIsSensitivePath('catalog/frontend/list_mode'));
    }

    public function testItMasksTheSamePathsForDatabaseToolsAndConfigurationTools(): void
    {
        $databaseTools = new DatabaseTools();
        $configurationTools = new ConfigurationTools();

        $dbRef = new \ReflectionClass($databaseTools);
        $dbMethod = $dbRef->getMethod('isSensitivePath');

        $ctRef = new \ReflectionClass($configurationTools);
        $ctMethod = $ctRef->getMethod('isSensitivePath');

        $testPaths = [
            'payment/paypal_express/active'                 => true,
            'payment'                                       => true,
            'carriers/flatrate/active'                      => true,
            'system/smtp/host'                              => true,
            'trans_email/ident_general/name'                => true,
            'oauth/access_token_lifetime/admin'             => true,
            'admin/security/password_reset_protection_type' => true,
            'catalog/search/elasticsearch7_server_hostname' => true,
            'catalog/search/opensearch_server_hostname'     => true,
            'general/store_information/name'                => false,
            'catalog/frontend/list_mode'                    => false,
            'web/unsecure/base_url'                         => false,
        ];

        foreach ($testPaths as $path => $expectedSensitive) {
            $dbResult = $dbMethod->invoke($databaseTools, $path);
            $ctResult = $ctMethod->invoke($configurationTools, $path);

            $this->assertSame(
                $expectedSensitive,
                $dbResult,
                "DatabaseTools::isSensitivePath('$path') expected " . ($expectedSensitive ? 'true' : 'false')
            );
            $this->assertSame(
                $dbResult,
                $ctResult,
                "isSensitivePath('$path') differs between DatabaseTools and ConfigurationTools"
            );
        }
    }

    public function testItUsesOneCanonicalSensitivePrefixList(): void
    {
        $traitName = MasksSensitiveConfig::class;

        $dbTraits = $this->getAllTraits(DatabaseTools::class);
        $ctTraits = $this->getAllTraits(ConfigurationTools::class);

        $this->assertContains(
            $traitName,
            $dbTraits,
            'DatabaseTools must use the MasksSensitiveConfig trait'
        );
        $this->assertContains(
            $traitName,
            $ctTraits,
            'ConfigurationTools must use the MasksSensitiveConfig trait'
        );
    }

    /**
     * Collect all trait names used by a class (including parent classes).
     *
     * @param class-string $class
     * @return list<string>
     */
    private function getAllTraits(string $class): array
    {
        $traits = [];
        $current = $class;
        while ($current !== false) {
            $traits = array_merge($traits, array_values(class_uses($current) ?: []));
            $current = get_parent_class($current);
        }
        return $traits;
    }
}
