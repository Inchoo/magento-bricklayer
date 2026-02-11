<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer;

use Inchoo\MagentoBricklayer\Command\InspectCommand;
use Inchoo\MagentoBricklayer\Command\InstallCommand;
use Inchoo\MagentoBricklayer\Command\McpServerCommand;
use Inchoo\MagentoBricklayer\Command\UpdateCommand;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Magento Bricklayer CLI Application
 *
 * The main CLI application class that registers all available commands.
 */
class Application extends ConsoleApplication
{
    /**
     * Application name
     */
    public const NAME = 'Magento Bricklayer';

    /**
     * Application version
     */
    public const VERSION = '1.0.0';

    public function __construct()
    {
        parent::__construct(self::NAME, self::VERSION);
        $this->registerCommands();
    }

    /**
     * Register all available commands
     *
     * @return void
     */
    private function registerCommands(): void
    {
        $this->add(new InstallCommand());
        $this->add(new McpServerCommand());
        $this->add(new InspectCommand());
        $this->add(new UpdateCommand());
    }

    /**
     * Get the application logo
     *
     * @return string
     */
    public static function getLogo(): string
    {
        return <<<'LOGO'
  ____       _      _    _
 | __ ) _ __(_) ___| | _| | __ _ _   _  ___ _ __
 |  _ \| '__| |/ __| |/ / |/ _` | | | |/ _ \ '__|
 | |_) | |  | | (__|   <| | (_| | |_| |  __/ |
 |____/|_|  |_|\___|_|\_\_|\__,_|\__, |\___|_|
                                 |___/
LOGO;
    }

    /**
     * @param InputInterface|null $input
     * @param OutputInterface|null $output
     * @return int
     */
    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        return parent::run($input, $output);
    }

    /**
     * @return string
     */
    public function getLongVersion(): string
    {
        return sprintf(
            '<info>%s</info> version <comment>%s</comment>',
            $this->getName(),
            $this->getVersion()
        );
    }
}
