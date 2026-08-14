<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Bootstrap;

use Inchoo\MagentoBricklayer\Bootstrap\AreaEmulator;
use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Bootstrap\MagentoDetector;
use PHPUnit\Framework\TestCase;

/**
 * Tests for B14 (MagentoBootstrap::reinitialize comment accuracy)
 * and B25 (AreaEmulator::setArea catch safety).
 */
class BootstrapCommentAndCatchTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        MagentoBootstrap::reset();
        $this->tmpDir = sys_get_temp_dir() . '/bricklayer_comment_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        MagentoBootstrap::reset();
        $this->removeDirectory($this->tmpDir);
    }

    // -------------------------------------------------------------------------
    // B14: reinitialize() comment accuracy
    // -------------------------------------------------------------------------

    /**
     * B14: The comment in reinitialize() says "Keep $magentoRoot and $detector",
     * but initialize() unconditionally does self::$detector = new MagentoDetector().
     * This test documents the real behaviour: the detector is re-created by
     * initialize(), not kept. The comment must be updated to reflect this.
     *
     * NOTE: this test exercises only the pre-initialize() portion of reinitialize()
     * and whatever initialize() does before throwing on an invalid Magento root.
     * Full reinitialize() behaviour requires a booted Magento installation and is
     * an integration concern only.
     */
    public function testItDocumentsReinitializeBehaviourAccuratelyForTheDetector(): void
    {
        // Inject a known detector instance via reflection
        $originalDetector = new MagentoDetector();
        $ref = new \ReflectionClass(MagentoBootstrap::class);

        $detectorProp = $ref->getProperty('detector');
        $detectorProp->setAccessible(true);
        $detectorProp->setValue(null, $originalDetector);

        $rootProp = $ref->getProperty('magentoRoot');
        $rootProp->setAccessible(true);
        $rootProp->setValue(null, $this->tmpDir); // invalid Magento root → will throw

        // reinitialize() will call initialize(), which runs self::$detector = new MagentoDetector()
        // before failing on the invalid root. So the detector is replaced, not kept.
        try {
            MagentoBootstrap::reinitialize();
        } catch (\Throwable $e) {
            // Expected: MagentoNotFoundException because $this->tmpDir has no Magento structure
        }

        $detectorAfter = $detectorProp->getValue(null);

        // The detector must be a new instance — initialize() re-creates it unconditionally.
        // This proves the "Keep $detector" comment is inaccurate and must be corrected.
        $this->assertNotSame(
            $originalDetector,
            $detectorAfter,
            'reinitialize() causes initialize() to re-create $detector; '
            . 'the "Keep $detector" comment is inaccurate and must be updated'
        );
    }

    // -------------------------------------------------------------------------
    // B25: AreaEmulator::setArea() catch safety
    // -------------------------------------------------------------------------

    /**
     * B25 happy path: setArea() completes without error when objectManager is available
     * and setAreaCode() succeeds on the State mock.
     *
     * NOTE: this test uses a plain anonymous-class fake with a setAreaCode() method
     * because Magento\Framework\App\State is not available as a compile-time
     * dependency in this standalone library. Any non-throwing setAreaCode() is
     * sufficient to verify the happy path.
     */
    public function testItSetsTheAreaWithoutErrorInTheHappyPath(): void
    {
        // Build a fake State that has setAreaCode() and does not throw
        $stateMock = new class {
            public ?string $areaCode = null;

            public function setAreaCode(string $areaCode): void
            {
                $this->areaCode = $areaCode;
            }

            public function getAreaCode(): ?string
            {
                return $this->areaCode;
            }
        };

        // Build an objectManager fake that returns the state fake
        $objectManagerMock = new class ($stateMock) {
            public function __construct(private object $state)
            {
            }

            public function get(string $type): object
            {
                return $this->state;
            }
        };

        // Inject objectManager into MagentoBootstrap static state
        $ref = new \ReflectionClass(MagentoBootstrap::class);
        $omProp = $ref->getProperty('objectManager');
        $omProp->setAccessible(true);
        $omProp->setValue(null, $objectManagerMock);

        $areaEmulator = new AreaEmulator();

        // Must not throw; area is set without error
        $areaEmulator->setArea('adminhtml');

        // Verify $currentArea was set to 'adminhtml' via reflection
        $emulatorRef = new \ReflectionClass(AreaEmulator::class);
        $currentAreaProp = $emulatorRef->getProperty('currentArea');
        $currentAreaProp->setAccessible(true);

        $this->assertSame('adminhtml', $currentAreaProp->getValue($areaEmulator));
    }

    /**
     * B25 catch safety: if $objectManager->get(State::class) itself throws
     * LocalizedException (instead of setAreaCode()), the catch block must not
     * reference an undefined $state variable.
     *
     * Before the fix: $state is only assigned inside the try block after get(),
     * so if get() throws LocalizedException, $state is undefined in the catch,
     * and $state->getAreaCode() throws Error (call on null/undefined).
     *
     * After the fix: $state is null-initialised before the try block (or the
     * catch is null-guarded), so no "undefined variable" / null-dereference error.
     *
     * NOTE: integration scenario — requires a booted Magento ObjectManager to
     * truly reproduce. Here we simulate it with a plain mock whose get() throws
     * an exception that the catch block would match in production.
     */
    public function testItDoesNotReferenceAnUndefinedStateVariableWhenGetStateThrows(): void
    {
        // Use LocalizedException directly — it is available via the outer project's vendor.
        $localizedException = new \Magento\Framework\Exception\LocalizedException(
            new \Magento\Framework\Phrase('Simulated: area already set')
        );

        // Build an objectManager fake whose get() throws LocalizedException
        $objectManagerMock = new class ($localizedException) {
            public function __construct(private \Throwable $exception)
            {
            }

            public function get(string $type): object
            {
                throw $this->exception;
            }
        };

        // Inject mock objectManager
        $ref = new \ReflectionClass(MagentoBootstrap::class);
        $omProp = $ref->getProperty('objectManager');
        $omProp->setAccessible(true);
        $omProp->setValue(null, $objectManagerMock);

        $areaEmulator = new AreaEmulator();

        // Before the fix: $state is undefined when get() throws, so the catch block's
        // $state->getAreaCode() call triggers a null-dereference Error.
        // After the fix: $state is null-initialised (or catch null-guards it) — no crash.
        try {
            $areaEmulator->setArea('adminhtml');
            // Reaching here is acceptable after the fix.
        } catch (\Error $e) {
            // An \Error here means the catch block tried to call ->getAreaCode() on
            // an undefined/null $state — this is the bug we're fixing.
            $this->fail(
                'setArea() crashed with Error because $state was undefined/null in catch: '
                . $e->getMessage()
            );
        } catch (\Throwable $e) {
            // Any other exception is fine — the undefined-variable bug manifests as Error.
        }

        // If we reached here without an Error, the fix is effective.
        $this->addToAssertionCount(1);
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
