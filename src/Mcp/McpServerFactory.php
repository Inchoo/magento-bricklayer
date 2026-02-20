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

        return "This MCP server provides comprehensive tools for Magento 2 development.\nCurrent installation: $magentoInfo\n\n" . <<<'INSTRUCTIONS'
Efficiency:
- For multi-step operations (bulk updates, cross-entity lookups, data aggregation), use code-runner to write PHP — one call replaces many individual tool calls.
- Use search-docs to find relevant tools before calling them. Do not explore all tools by trial and error.
- For list tools (product-list, order-list, etc.), use count_only=true to check result size before fetching. Use fields parameter to request only needed columns.
- Use verbosity=minimal on module-list and eav-attributes when you only need identifiers.

code-runner examples:

Batch product lookup:
$skus = ['SKU1', 'SKU2', 'SKU3'];
$repo = repo(\Magento\Catalog\Api\ProductRepositoryInterface::class);
$results = [];
foreach ($skus as $sku) {
    $p = $repo->get($sku);
    $results[$sku] = ['name' => $p->getName(), 'price' => $p->getPrice()];
}
return $results;

Cross-entity query:
$order = repo(\Magento\Sales\Api\OrderRepositoryInterface::class)->get($orderId);
$customer = repo(\Magento\Customer\Api\CustomerRepositoryInterface::class)->getById($order->getCustomerId());
return ['order' => $order->getIncrementId(), 'customer' => $customer->getEmail()];

SQL aggregation:
$result = query("SELECT status, COUNT(*) as cnt FROM sales_order GROUP BY status");
return $result;

Always use introspection tools before generating code to understand existing codebase structure. Before writing code, call development-context with the relevant task category to load coding guidelines.
INSTRUCTIONS;
    }
}
