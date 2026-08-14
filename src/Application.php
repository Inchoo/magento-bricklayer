<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer;

use Inchoo\MagentoBricklayer\Command\ConfigSetCommand;
use Inchoo\MagentoBricklayer\Command\InitCommand;
use Inchoo\MagentoBricklayer\Command\InspectCommand;
use Inchoo\MagentoBricklayer\Command\InstallCommand;
use Inchoo\MagentoBricklayer\Command\McpServerCommand;
use Inchoo\MagentoBricklayer\Command\UpdateCommand;
use Inchoo\MagentoBricklayer\Command\VerifyCommand;
use Symfony\Component\Console\Application as ConsoleApplication;

class Application extends ConsoleApplication
{
    public const NAME = 'Magento Bricklayer';

    private static ?string $version = null;

    public function __construct()
    {
        parent::__construct(self::NAME, self::getComposerVersion());
        $this->registerCommands();
    }

    public static function getComposerVersion(): string
    {
        if (self::$version === null) {
            $composerFile = dirname(__DIR__) . '/composer.json';
            $data = json_decode((string) file_get_contents($composerFile), true);
            self::$version = $data['version'] ?? 'dev';
        }

        return self::$version;
    }

    private function registerCommands(): void
    {
        $this->add(new ConfigSetCommand());
        $this->add(new InitCommand());
        $this->add(new InstallCommand());
        $this->add(new McpServerCommand());
        $this->add(new InspectCommand());
        $this->add(new UpdateCommand());
        $this->add(new VerifyCommand());
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
