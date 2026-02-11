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
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class McpServerFactory
{
    private const SERVER_NAME = 'magento-bricklayer';
    private const SERVER_VERSION = '1.0.0';

    public function create(?LoggerInterface $logger = null): Server
    {
        $logger = $logger ?? new NullLogger();

        $this->initializeMagento($logger);

        $container = new Container();
        $container->set(LoggerInterface::class, $logger);

        return Server::builder()
            ->setServerInfo(self::SERVER_NAME, self::SERVER_VERSION)
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
        $magentoInfo = 'Magento installation not detected';

        if (MagentoBootstrap::isInitialized()) {
            try {
                $metadata = MagentoBootstrap::get(\Magento\Framework\App\ProductMetadataInterface::class);
                $version = $metadata->getVersion();
                $edition = $metadata->getEdition();
                $magentoInfo = "Magento $edition $version";
            } catch (\Throwable $e) {
                $magentoInfo = 'Magento (version unknown)';
            }
        }

        return <<<INSTRUCTIONS
This MCP server provides comprehensive tools for Magento 2 development.
Current installation: $magentoInfo

Use the available tools to inspect the Magento installation, query the database,
understand module structures, and generate code following Magento best practices.

Key capabilities:
- Inspect installed modules and their structure
- Query database schema and EAV attributes
- View DI configuration, plugins, and preferences
- Manage products, orders, and customers
- Search Magento documentation

Always use the introspection tools before generating code to understand
the existing codebase structure and conventions.

Before writing or generating code, call the `development-context` tool with the relevant task category to load coding guidelines and development patterns.
INSTRUCTIONS;
    }
}
