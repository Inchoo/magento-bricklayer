<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Command;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoDetector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Abstract base command providing the shared magento-root option and root-resolution logic.
 *
 * All Bricklayer commands extend this class so that the --magento-root / -m option
 * is registered once and resolution (rtrim + is_dir guard) is never duplicated.
 */
abstract class AbstractBricklayerCommand extends Command
{
    /** Shared error message when no Magento root can be resolved. */
    protected const ERROR_NO_MAGENTO = 'Could not detect Magento installation. Please specify --magento-root option.';

    /**
     * Register the shared --magento-root / -m option.
     *
     * Subclasses must call parent::configure() when they override configure().
     */
    protected function configure(): void
    {
        $this->addOption(
            'magento-root',
            'm',
            InputOption::VALUE_OPTIONAL,
            'Path to Magento root directory (auto-detected if not specified)'
        );
    }

    /**
     * Resolve the Magento root directory from the --magento-root option or auto-detection.
     *
     * Returns the rtrim-normalised absolute path on success, or null on failure (after
     * writing an error message via $io).
     */
    protected function resolveMagentoRoot(InputInterface $input, SymfonyStyle $io): ?string
    {
        $magentoRoot = $input->getOption('magento-root') ?? (new MagentoDetector())->detect();

        if ($magentoRoot === null) {
            $io->error(self::ERROR_NO_MAGENTO);
            return null;
        }

        $magentoRoot = rtrim((string) $magentoRoot, '/\\');

        if (!is_dir($magentoRoot)) {
            $io->error("Directory does not exist: {$magentoRoot}");
            return null;
        }

        return $magentoRoot;
    }

    /**
     * Resolve and normalise the Magento root from --magento-root or auto-detection WITHOUT
     * emitting output. Returns the rtrim-normalised path, or null when nothing is provided/
     * detected or the path is not an existing directory.
     *
     * For commands that need custom failure handling (JSON output, or running checks against
     * a possibly-absent root) instead of the fail-fast resolveMagentoRoot() error emission.
     */
    protected function detectMagentoRoot(InputInterface $input): ?string
    {
        $magentoRoot = $input->getOption('magento-root') ?? (new MagentoDetector())->detect();

        if ($magentoRoot === null) {
            return null;
        }

        $magentoRoot = rtrim((string) $magentoRoot, '/\\');

        return is_dir($magentoRoot) ? $magentoRoot : null;
    }
}
