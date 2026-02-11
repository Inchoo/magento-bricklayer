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

class Application extends ConsoleApplication
{
    public const NAME = 'Magento Bricklayer';
    public const VERSION = '1.0.0';

    public function __construct()
    {
        parent::__construct(self::NAME, self::VERSION);
        $this->registerCommands();
    }

    private function registerCommands(): void
    {
        $this->add(new InstallCommand());
        $this->add(new McpServerCommand());
        $this->add(new InspectCommand());
        $this->add(new UpdateCommand());
    }

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

    public function getLongVersion(): string
    {
        return sprintf(
            '<info>%s</info> version <comment>%s</comment>',
            $this->getName(),
            $this->getVersion()
        );
    }
}
