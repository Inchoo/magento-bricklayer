<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp;

/**
 * MCP Server
 *
 * @deprecated Use McpServerFactory to create MCP server instances.
 * @see McpServerFactory
 */
class Server
{
    /**
     * @deprecated Use McpServerFactory::create() instead
     */
    public function __construct()
    {
        trigger_error(
            'Direct instantiation of Server is deprecated. Use McpServerFactory instead.',
            E_USER_DEPRECATED
        );
    }

    /**
     * @deprecated Use McpServerFactory::create() and run via transport
     */
    public function run(): void
    {
        throw new \RuntimeException(
            'This method is deprecated. Use McpServerFactory::create() to get a server instance, ' .
            'then call $server->run($transport) with a transport implementation.'
        );
    }
}
