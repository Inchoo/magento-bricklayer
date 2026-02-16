<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Command;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoDetector;
use Inchoo\MagentoBricklayer\Guidelines\GuidelinesCompiler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Update Command
 *
 * Regenerates agent configuration files.
 */
class UpdateCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'update';

    /**
     * @var string
     */
    protected static $defaultDescription = 'Regenerate agent configuration files';

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                'magento-root',
                'm',
                InputOption::VALUE_OPTIONAL,
                'Path to Magento root directory (auto-detected if not specified)'
            )
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command regenerates agent configuration files:

  <info>%command.full_name%</info>
HELP
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Magento Bricklayer Update');

        $detector = new MagentoDetector();
        $magentoRoot = $input->getOption('magento-root') ?? $detector->detect();

        if ($magentoRoot === null) {
            $io->error('Could not detect Magento installation. Please specify --magento-root option.');
            return Command::FAILURE;
        }

        $io->section('Regenerating configuration files...');

        try {
            $compiler = new GuidelinesCompiler();
            $envType = $detector->getEnvironmentType($magentoRoot);

            $agentFiles = [
                'CLAUDE.md' => 'claude-code',
                '.cursorrules' => 'cursor',
                '.github/copilot-instructions.md' => 'copilot',
                '.junie/guidelines.md' => 'phpstorm',
                'AGENTS.md' => 'gemini',
            ];

            $regenerated = [];

            foreach ($agentFiles as $file => $agent) {
                $filepath = $magentoRoot . '/' . $file;
                if (file_exists($filepath)) {
                    $content = $compiler->compile($agent, $envType);
                    file_put_contents($filepath, $content);
                    $regenerated[] = $file;
                }
            }

            if (!empty($regenerated)) {
                foreach ($regenerated as $file) {
                    $io->text("  <info>✓</info> Regenerated $file");
                }
            } else {
                $io->text('  <comment>No configuration files found to regenerate</comment>');
                $io->text('  Run <info>bricklayer install</info> to create configuration files');
            }
        } catch (\Throwable $e) {
            $io->error('Failed to regenerate configuration: ' . $e->getMessage());
            $io->newLine();
            $io->warning('Update completed with errors');
            return Command::FAILURE;
        }

        $io->newLine();
        $io->success('Update complete!');
        return Command::SUCCESS;
    }
}
