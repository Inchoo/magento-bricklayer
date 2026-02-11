<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Integration;

use Inchoo\MagentoBricklayer\Bootstrap\MagentoDetector;

/**
 * MCP Configuration Writer
 *
 * Generates MCP server configuration files for various AI agents.
 */
class McpConfigWriter
{
    /**
     * @param string $projectRoot The project root directory
     */
    public function __construct(
        private readonly string $projectRoot
    ) {
    }

    /**
     * Write MCP configuration file
     *
     * @param string $envType The environment type (native, docker-compose, ddev, hooli, warden)
     * @return void
     */
    public function writeMcpConfig(string $envType = 'native'): void
    {
        $config = [
            'mcpServers' => [
                'magento-bricklayer' => $this->getMcpServerConfig($envType),
            ],
        ];

        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($this->projectRoot . '/.mcp.json', $json . "\n");
    }

    /**
     * Get MCP server configuration based on environment type
     *
     * @param string $envType The environment type
     * @return array<string, mixed>
     */
    private function getMcpServerConfig(string $envType): array
    {
        return match ($envType) {
            'ddev' => [
                'command' => 'ddev',
                'args' => ['exec', 'php', 'vendor/bin/bricklayer-mcp'],
            ],
            'hooli' => [
                'command' => 'docker',
                'args' => ['compose', '-f', '../docker-compose.yml', 'exec', '-T', 'apache-php', 'php', 'vendor/bin/bricklayer-mcp'],
            ],
            'warden' => [
                'command' => 'warden',
                'args' => ['shell', '-c', 'php vendor/bin/bricklayer-mcp'],
            ],
            'docker-compose' => [
                'command' => 'docker',
                'args' => ['compose', 'exec', '-T', $this->detectPhpService(), 'php', 'vendor/bin/bricklayer-mcp'],
            ],
            'docker' => [
                'command' => 'docker',
                'args' => ['exec', '-i', $this->detectContainerName(), 'php', 'vendor/bin/bricklayer-mcp'],
            ],
            default => [
                'command' => 'php',
                'args' => ['vendor/bin/bricklayer-mcp'],
            ],
        };
    }

    /**
     * Detect PHP service name from docker-compose.yml
     *
     * @return string The PHP service name
     */
    private function detectPhpService(): string
    {
        $composeFiles = [
            $this->projectRoot . '/docker-compose.yml',
            $this->projectRoot . '/compose.yml',
            $this->projectRoot . '/docker-compose.yaml',
        ];

        foreach ($composeFiles as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                if ($content === false) {
                    continue;
                }

                // Simple detection of common PHP service names
                $phpServices = ['php', 'php-fpm', 'phpfpm', 'app', 'web', 'magento'];
                foreach ($phpServices as $service) {
                    // Check if service exists in the file (basic YAML parsing)
                    if (preg_match('/^\s*' . preg_quote($service, '/') . ':/m', $content)) {
                        return $service;
                    }
                }
            }
        }

        return 'php';
    }

    /**
     * Detect container name for Docker
     *
     * @return string The container name
     */
    private function detectContainerName(): string
    {
        // Try to get from environment or use a sensible default
        $containerName = getenv('BRICKLAYER_CONTAINER_NAME');
        if ($containerName !== false && $containerName !== '') {
            return $containerName;
        }

        // Default to project directory name + php
        $projectName = basename($this->projectRoot);
        return $projectName . '-php';
    }

    /**
     * Write Cursor-specific MCP configuration
     *
     * @param string $envType The environment type
     * @return void
     */
    public function writeCursorConfig(string $envType = 'native'): void
    {
        // Cursor uses the same .mcp.json format
        $this->writeMcpConfig($envType);
    }

    /**
     * Write PhpStorm-specific MCP configuration
     *
     * @param string $envType The environment type
     * @return void
     */
    public function writePhpStormConfig(string $envType = 'native'): void
    {
        $ideaDir = $this->projectRoot . '/.idea';
        if (!is_dir($ideaDir)) {
            mkdir($ideaDir, 0755, true);
        }

        $config = [
            'mcpServers' => [
                'magento-bricklayer' => $this->getMcpServerConfig($envType),
            ],
        ];

        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($ideaDir . '/mcp.json', $json . "\n");
    }

    /**
     * Get available environment types
     *
     * @return array<string>
     */
    public static function getAvailableEnvironmentTypes(): array
    {
        return ['native', 'docker', 'docker-compose', 'ddev', 'hooli', 'warden'];
    }
}
