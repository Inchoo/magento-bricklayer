<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Support;

use Inchoo\MagentoBricklayer\Support\ComponentRegistration;
use PHPUnit\Framework\TestCase;

class ComponentRegistrationTest extends TestCase
{
    private ComponentRegistration $registration;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->registration = new ComponentRegistration();
        $this->tmpDir = sys_get_temp_dir() . '/bricklayer_compreg_test_' . uniqid();
        mkdir($this->tmpDir . '/app/etc', 0755, true);

        file_put_contents(
            $this->tmpDir . '/app/etc/registration_globlist.php',
            '<?php return ["app/code/*/*/registration.php"];'
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    // -------------------------------------------------------------------------
    // registerNewComponents()
    // -------------------------------------------------------------------------

    public function testRegisterNewComponentsLoadsNewFilesExactlyOnce(): void
    {
        $marker = 'bricklayer_test_marker_' . uniqid();
        $this->createModuleRegistrationFile('Acme', 'One', $marker);

        $first = $this->registration->registerNewComponents($this->tmpDir);

        self::assertCount(1, $first, 'new registration.php must be loaded');
        self::assertSame(1, $GLOBALS[$marker] ?? 0, 'file must have executed once');

        $second = $this->registration->registerNewComponents($this->tmpDir);

        self::assertSame([], $second, 'already-loaded file must not be reloaded');
        self::assertSame(1, $GLOBALS[$marker] ?? 0, 'file must not execute twice');
    }

    public function testRegisterNewComponentsPicksUpFilesCreatedAfterFirstScan(): void
    {
        $markerOne = 'bricklayer_test_marker_' . uniqid();
        $this->createModuleRegistrationFile('Acme', 'First', $markerOne);
        $this->registration->registerNewComponents($this->tmpDir);

        // Simulate a module created mid-session (the exact scenario the MCP
        // server previously could not handle without a restart).
        $markerTwo = 'bricklayer_test_marker_' . uniqid();
        $this->createModuleRegistrationFile('Acme', 'Second', $markerTwo);

        $new = $this->registration->registerNewComponents($this->tmpDir);

        self::assertCount(1, $new, 'only the newly created file loads');
        self::assertStringContainsString('Second', $new[0]);
        self::assertSame(1, $GLOBALS[$markerTwo] ?? 0);
        self::assertSame(1, $GLOBALS[$markerOne] ?? 0, 'first module must not re-execute');
    }

    public function testRegisterNewComponentsToleratesMissingGloblist(): void
    {
        $emptyRoot = $this->tmpDir . '/empty';
        mkdir($emptyRoot, 0755, true);

        self::assertSame([], $this->registration->registerNewComponents($emptyRoot));
    }

    public function testRegisterNewComponentsToleratesNonArrayGloblist(): void
    {
        file_put_contents(
            $this->tmpDir . '/app/etc/registration_globlist.php',
            '<?php return "not-an-array";'
        );

        self::assertSame([], $this->registration->registerNewComponents($this->tmpDir));
    }

    // -------------------------------------------------------------------------
    // registerNewVendorComponents()
    // -------------------------------------------------------------------------

    public function testRegisterNewVendorComponentsLoadsNewAutoloadFiles(): void
    {
        $marker = 'bricklayer_test_vendor_marker_' . uniqid();
        mkdir($this->tmpDir . '/vendor/composer', 0755, true);
        mkdir($this->tmpDir . '/vendor/acme/lib', 0755, true);

        $libFile = $this->tmpDir . '/vendor/acme/lib/functions.php';
        file_put_contents($libFile, $this->markerIncrementCode($marker));
        file_put_contents(
            $this->tmpDir . '/vendor/composer/autoload_files.php',
            '<?php return ["hash1" => ' . var_export($libFile, true) . '];'
        );

        $first = $this->registration->registerNewVendorComponents($this->tmpDir);
        $second = $this->registration->registerNewVendorComponents($this->tmpDir);

        self::assertCount(1, $first);
        self::assertSame([], $second);
        self::assertSame(1, $GLOBALS[$marker] ?? 0);
    }

    public function testRegisterNewVendorComponentsToleratesMissingAutoloadFiles(): void
    {
        self::assertSame([], $this->registration->registerNewVendorComponents($this->tmpDir));
    }

    // -------------------------------------------------------------------------
    // getEnabledModules()
    // -------------------------------------------------------------------------

    public function testGetEnabledModulesReadsConfigPhpAndFiltersDisabled(): void
    {
        $this->writeConfigPhp(['Acme_Enabled' => 1, 'Acme_Disabled' => 0]);

        self::assertSame(['Acme_Enabled'], $this->registration->getEnabledModules($this->tmpDir));
    }

    public function testGetEnabledModulesToleratesMissingOrMalformedConfig(): void
    {
        self::assertSame(
            [],
            $this->registration->getEnabledModules($this->tmpDir . '/nowhere')
        );

        file_put_contents($this->tmpDir . '/app/etc/config.php', '<?php return "garbage";');

        self::assertSame([], $this->registration->getEnabledModules($this->tmpDir));
    }

    // -------------------------------------------------------------------------
    // getUnregisteredEnabledModules() / getRemovedRegisteredModules()
    //
    // These use the real \Magento\Framework\Component\ComponentRegistrar from
    // the monorepo root vendor. Its registry is process-global and cannot be
    // unregistered, so every test uses uniquely named fixture modules and
    // asserts only about those names.
    // -------------------------------------------------------------------------

    public function testGetUnregisteredEnabledModulesReportsOnlyMissingOnes(): void
    {
        $registeredName = 'BricklayerTest_Registered' . uniqid();
        $unregisteredName = 'BricklayerTest_Ghost' . uniqid();

        $modulePath = $this->tmpDir . '/app/code/BricklayerTest/Registered';
        mkdir($modulePath, 0755, true);
        \Magento\Framework\Component\ComponentRegistrar::register(
            \Magento\Framework\Component\ComponentRegistrar::MODULE,
            $registeredName,
            $modulePath
        );

        $this->writeConfigPhp([$registeredName => 1, $unregisteredName => 1]);

        $unregistered = $this->registration->getUnregisteredEnabledModules($this->tmpDir);

        self::assertContains($unregisteredName, $unregistered);
        self::assertNotContains($registeredName, $unregistered);
    }

    public function testGetRemovedRegisteredModulesDetectsDeletedModuleDir(): void
    {
        $removedName = 'BricklayerTest_Removed' . uniqid();
        $keptName = 'BricklayerTest_Kept' . uniqid();

        $removedPath = $this->tmpDir . '/app/code/BricklayerTest/Removed';
        $keptPath = $this->tmpDir . '/app/code/BricklayerTest/Kept';
        mkdir($removedPath, 0755, true);
        mkdir($keptPath, 0755, true);

        \Magento\Framework\Component\ComponentRegistrar::register(
            \Magento\Framework\Component\ComponentRegistrar::MODULE,
            $removedName,
            $removedPath
        );
        \Magento\Framework\Component\ComponentRegistrar::register(
            \Magento\Framework\Component\ComponentRegistrar::MODULE,
            $keptName,
            $keptPath
        );

        rmdir($removedPath);
        clearstatcache();

        $removed = $this->registration->getRemovedRegisteredModules();

        self::assertContains($removedName, $removed);
        self::assertNotContains($keptName, $removed);
    }

    public function testGetRemovedEnabledModulesIgnoresDisabledRemovedModules(): void
    {
        $enabledRemoved = 'BricklayerTest_EnabledRemoved' . uniqid();
        $disabledRemoved = 'BricklayerTest_DisabledRemoved' . uniqid();

        foreach ([$enabledRemoved => 'EnabledRemoved', $disabledRemoved => 'DisabledRemoved'] as $name => $dir) {
            $path = $this->tmpDir . '/app/code/BricklayerTest/' . $dir;
            mkdir($path, 0755, true);
            \Magento\Framework\Component\ComponentRegistrar::register(
                \Magento\Framework\Component\ComponentRegistrar::MODULE,
                $name,
                $path
            );
            rmdir($path);
        }
        clearstatcache();

        $this->writeConfigPhp([$enabledRemoved => 1, $disabledRemoved => 0]);

        $removed = $this->registration->getRemovedEnabledModules($this->tmpDir);

        self::assertContains($enabledRemoved, $removed);
        self::assertNotContains($disabledRemoved, $removed);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createModuleRegistrationFile(string $vendor, string $module, string $marker): void
    {
        $dir = $this->tmpDir . '/app/code/' . $vendor . '/' . $module;
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/registration.php', $this->markerIncrementCode($marker));
    }

    private function markerIncrementCode(string $marker): string
    {
        $key = var_export($marker, true);

        return '<?php $GLOBALS[' . $key . '] = ($GLOBALS[' . $key . '] ?? 0) + 1;';
    }

    /**
     * @param array<string, int> $modules
     */
    private function writeConfigPhp(array $modules): void
    {
        file_put_contents(
            $this->tmpDir . '/app/etc/config.php',
            '<?php return ' . var_export(['modules' => $modules], true) . ';'
        );
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff((array) scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
