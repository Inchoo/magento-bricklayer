<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Command;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoDetector;
use Inchoo\MagentoBricklayer\Config\ConfigInitializer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Init Command
 *
 * Generates .bricklayer.json configuration file with smart defaults.
 */
class InitCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'init';

    /**
     * @var string
     */
    protected static $defaultDescription = 'Generate .bricklayer.json configuration file';

    protected function configure(): void
    {
        $this
            ->addOption(
                'magento-root',
                'm',
                InputOption::VALUE_OPTIONAL,
                'Path to Magento root directory (auto-detected if not specified)'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite existing .bricklayer.json'
            )
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command generates a <comment>.bricklayer.json</comment> configuration file:

  <info>%command.full_name%</info>

The generated configuration includes smart defaults based on your Magento deploy mode:
  - <comment>production</comment>: Restrictive defaults (destructive tools disabled, code-runner disabled)
  - <comment>developer/default</comment>: Permissive defaults (all tools enabled, code-runner read-only)

Use <comment>--force</comment> to overwrite an existing file:

  <info>%command.full_name% --force</info>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $detector = new MagentoDetector();
        $magentoRoot = $input->getOption('magento-root') ?? $detector->detect();

        if ($magentoRoot === null) {
            $io->error('Could not detect Magento installation. Please specify --magento-root option.');
            return Command::FAILURE;
        }

        $force = $input->getOption('force');
        $initializer = new ConfigInitializer();
        $result = $initializer->generate($magentoRoot, $force);

        if (!$result['created']) {
            $io->text('  <comment>⊘</comment> .bricklayer.json already exists (use --force to overwrite)');
            return Command::SUCCESS;
        }

        $io->text(sprintf(
            '  <info>✓</info> Created .bricklayer.json (based on <comment>%s</comment> mode)',
            $result['deploy_mode']
        ));

        return Command::SUCCESS;
    }
}
