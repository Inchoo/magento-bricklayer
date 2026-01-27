<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Bootstrap;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoDetector;
use PHPUnit\Framework\TestCase;

class MagentoDetectorTest extends TestCase
{
    private MagentoDetector $detector;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->detector = new MagentoDetector();
        $this->tempDir = sys_get_temp_dir() . '/bricklayer_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testDetectReturnsNullForEmptyDirectory(): void
    {
        $result = $this->detector->detect($this->tempDir);
        $this->assertNull($result);
    }

    public function testDetectReturnsMagentoRootWhenValid(): void
    {
        $this->createMagentoStructure($this->tempDir);

        $result = $this->detector->detect($this->tempDir);
        $this->assertEquals($this->tempDir, $result);
    }

    public function testDetectFindsRootFromSubdirectory(): void
    {
        $this->createMagentoStructure($this->tempDir);

        $subDir = $this->tempDir . '/app/code/Vendor/Module';
        mkdir($subDir, 0755, true);

        $result = $this->detector->detect($subDir);
        $this->assertEquals($this->tempDir, $result);
    }

    public function testIsValidMagentoRootReturnsTrueForValidRoot(): void
    {
        $this->createMagentoStructure($this->tempDir);

        $result = $this->detector->isValidMagentoRoot($this->tempDir);
        $this->assertTrue($result);
    }

    public function testIsValidMagentoRootReturnsFalseForInvalidRoot(): void
    {
        $result = $this->detector->isValidMagentoRoot($this->tempDir);
        $this->assertFalse($result);
    }

    public function testGetEditionReturnsUnknownForMissingComposer(): void
    {
        $result = $this->detector->getEdition($this->tempDir);
        $this->assertEquals('unknown', $result);
    }

    public function testGetEditionReturnsCommunityForCommunityEdition(): void
    {
        $composerContent = json_encode([
            'require' => [
                'magento/product-community-edition' => '2.4.7',
            ],
        ]);
        file_put_contents($this->tempDir . '/composer.json', $composerContent);

        $result = $this->detector->getEdition($this->tempDir);
        $this->assertEquals('community', $result);
    }

    public function testGetEditionReturnsEnterpriseForEnterpriseEdition(): void
    {
        $composerContent = json_encode([
            'require' => [
                'magento/product-enterprise-edition' => '2.4.7',
            ],
        ]);
        file_put_contents($this->tempDir . '/composer.json', $composerContent);

        $result = $this->detector->getEdition($this->tempDir);
        $this->assertEquals('enterprise', $result);
    }

    public function testGetVersionParsesVersionCorrectly(): void
    {
        $composerContent = json_encode([
            'require' => [
                'magento/product-community-edition' => '^2.4.7',
            ],
        ]);
        file_put_contents($this->tempDir . '/composer.json', $composerContent);

        $result = $this->detector->getVersion($this->tempDir);
        $this->assertEquals('2.4.7', $result);
    }

    public function testGetEnvironmentTypeReturnsNativeByDefault(): void
    {
        $result = $this->detector->getEnvironmentType($this->tempDir);
        $this->assertEquals('native', $result);
    }

    public function testGetEnvironmentTypeReturnsDdevWhenDdevConfigExists(): void
    {
        mkdir($this->tempDir . '/.ddev', 0755, true);
        file_put_contents($this->tempDir . '/.ddev/config.yaml', 'name: test');

        $result = $this->detector->getEnvironmentType($this->tempDir);
        $this->assertEquals('ddev', $result);
    }

    public function testGetEnvironmentTypeReturnsDockerComposeWhenComposeExists(): void
    {
        file_put_contents($this->tempDir . '/docker-compose.yml', 'version: "3"');

        $result = $this->detector->getEnvironmentType($this->tempDir);
        $this->assertEquals('docker-compose', $result);
    }

    private function createMagentoStructure(string $baseDir): void
    {
        $dirs = [
            '/app/etc',
            '/bin',
        ];

        foreach ($dirs as $dir) {
            mkdir($baseDir . $dir, 0755, true);
        }

        file_put_contents($baseDir . '/app/bootstrap.php', '<?php // bootstrap');
        file_put_contents($baseDir . '/app/etc/env.php', '<?php return [];');
        file_put_contents($baseDir . '/bin/magento', '#!/usr/bin/env php');
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
