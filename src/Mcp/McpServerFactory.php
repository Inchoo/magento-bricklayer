<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Mcp\Capability\Registry\Container;
use Mcp\Schema\ServerCapabilities;
use Mcp\Server;
use Inchoo\MagentoBricklayer\Application;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class McpServerFactory
{
    private const SERVER_NAME = 'magento-bricklayer';

    public function create(?LoggerInterface $logger = null): Server
    {
        $logger = $logger ?? new NullLogger();

        $this->initializeMagento($logger);

        $container = new Container();
        $container->set(LoggerInterface::class, $logger);

        return Server::builder()
            ->setServerInfo(self::SERVER_NAME, Application::getComposerVersion())
            ->setLogger($logger)
            ->setContainer($container)
            ->setInstructions($this->getServerInstructions())
            ->setDiscovery(__DIR__, ['Tool', 'Resource', 'Prompt'])
            ->setPaginationLimit(200)
            ->setCapabilities(new ServerCapabilities(
                tools: true,
                toolsListChanged: false,
                resources: true,
                resourcesSubscribe: false,
                resourcesListChanged: false,
                prompts: true,
                promptsListChanged: false,
                logging: false,
                completions: false,
            ))
            ->build();
    }

    private function initializeMagento(LoggerInterface $logger): void
    {
        try {
            $magentoRoot = getenv('BRICKLAYER_MAGENTO_ROOT') ?: null;
            MagentoBootstrap::initialize($magentoRoot);
            $logger->info('Magento initialized successfully');
        } catch (\Throwable $e) {
            // Continue without Magento - some tools may still work
            $logger->warning('Magento initialization failed: ' . $e->getMessage());
        }
    }

    private function getServerInstructions(): string
    {
        $edition = 'Community';
        $version = 'unknown';
        $mode = 'unknown';

        if (MagentoBootstrap::isInitialized()) {
            try {
                $metadata = MagentoBootstrap::get(\Magento\Framework\App\ProductMetadataInterface::class);
                $edition = $metadata->getEdition();
                $version = $metadata->getVersion();
            } catch (\Throwable) {
            }

            try {
                $state = MagentoBootstrap::get(\Magento\Framework\App\State::class);
                $mode = $state->getMode();
            } catch (\Throwable) {
            }
        }

        $toolCount = 80;

        return <<<INSTRUCTIONS
        Magento {$edition} {$version} ({$mode} mode).
        CRITICAL: Magento resolves DI, plugins, preferences, and events at runtime across modules. Reading source files alone misses overrides from other modules. Before modifying any class:
        - check-class: combined plugin, DI, and preference check for any class
        - eav-attributes: custom product/customer attributes (exist in DB, not code)
        - diagnose-error: first step for any error (combines logs + DI + plugin context)
        - diagnose-performance: indexes, cache, cron, config, query analysis
        Use development-context to load coding guidelines before writing code.
        Use search-tools to discover all {$toolCount} tools (only 16 shown initially).
        Use code-runner for multi-step operations instead of chaining individual tools.
        On list tools: use fields to limit response, count_only=true to check size.
        INSTRUCTIONS;
    }
}
