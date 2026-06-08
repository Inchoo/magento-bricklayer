<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Command;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoDetector;
use Inchoo\MagentoBricklayer\Guidelines\GuidelinesCompiler;
use Inchoo\MagentoBricklayer\Mcp\Tool\SearchTools;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Update Command
 *
 * Regenerates agent configuration files.
 */
#[AsCommand(
    name: 'update',
    description: 'Regenerate agent configuration files'
)]
class UpdateCommand extends AbstractBricklayerCommand
{
    /**
     * @return void
     */
    protected function configure(): void
    {
        parent::configure();
        $this
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

        $magentoRoot = $this->resolveMagentoRoot($input, $io);
        if ($magentoRoot === null) {
            return Command::FAILURE;
        }

        $io->section('Regenerating configuration files...');

        try {
            $compiler = new GuidelinesCompiler($magentoRoot);
            $detector = new MagentoDetector();
            $envType = $detector->getEnvironmentType($magentoRoot);

            $agents = ['claude-code', 'cursor', 'copilot', 'phpstorm', 'gemini'];

            $regenerated = [];
            $appliedOverrides = [];

            foreach ($agents as $agent) {
                $file = $compiler->getFilename($agent);
                $filepath = $magentoRoot . '/' . $file;
                if (file_exists($filepath)) {
                    $content = $compiler->compile($agent, $envType);
                    file_put_contents($filepath, $content);
                    $regenerated[] = $file;
                    $appliedOverrides = $compiler->getAppliedOverrides();
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

            if (!empty($appliedOverrides)) {
                $io->text('  <info>Applied local overrides:</info>');
                foreach ($appliedOverrides as $path) {
                    $io->text('    - ' . $path);
                }
            }

            $searchTools = new SearchTools($magentoRoot);
            $localEntryCount = $searchTools->getLocalDocumentationEntryCount();
            if ($localEntryCount > 0) {
                $io->text(sprintf(
                    '  <info>Indexed %d local file(s) into docs index</info>',
                    $localEntryCount
                ));
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
