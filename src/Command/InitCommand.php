<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Command;

use Inchoo\MagentoBricklayer\Config\ConfigInitializer;
use Symfony\Component\Console\Attribute\AsCommand;
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
#[AsCommand(
    name: 'init',
    description: 'Generate .bricklayer.json configuration file'
)]
class InitCommand extends AbstractBricklayerCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
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

        $magentoRoot = $this->resolveMagentoRoot($input, $io);
        if ($magentoRoot === null) {
            return Command::FAILURE;
        }

        $force = $input->getOption('force');
        $initializer = new ConfigInitializer();
        $result = $initializer->generate($magentoRoot, $force);

        $bricklayerDir = $magentoRoot . '/.bricklayer';
        $createdDir = false;
        if (!is_dir($bricklayerDir)) {
            if (@mkdir($bricklayerDir, 0755, true) || is_dir($bricklayerDir)) {
                $createdDir = true;
            }
        }

        if (!$result['created']) {
            $io->text('  <comment>⊘</comment> .bricklayer.json already exists (use --force to overwrite)');
            if ($createdDir) {
                $io->text('  <info>✓</info> Created .bricklayer/ directory for project overrides');
            }
            return Command::SUCCESS;
        }

        $io->text(sprintf(
            '  <info>✓</info> Created .bricklayer.json (based on <comment>%s</comment> mode)',
            $result['deploy_mode']
        ));

        if ($createdDir) {
            $io->text('  <info>✓</info> Created .bricklayer/ directory for project overrides');
        }

        return Command::SUCCESS;
    }
}
