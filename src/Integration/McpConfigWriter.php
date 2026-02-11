<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Integration;

class McpConfigWriter
{
    public function __construct(
        private readonly string $projectRoot
    ) {
    }

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

    /** @return array<string, mixed> */
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

                $phpServices = ['php', 'php-fpm', 'phpfpm', 'app', 'web', 'magento'];
                foreach ($phpServices as $service) {
                    if (preg_match('/^\s*' . preg_quote($service, '/') . ':/m', $content)) {
                        return $service;
                    }
                }
            }
        }

        return 'php';
    }

    private function detectContainerName(): string
    {
        $containerName = getenv('BRICKLAYER_CONTAINER_NAME');
        if ($containerName !== false && $containerName !== '') {
            return $containerName;
        }

        $projectName = basename($this->projectRoot);
        return $projectName . '-php';
    }

    public function writeCursorConfig(string $envType = 'native'): void
    {
        $this->writeMcpConfig($envType);
    }

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

    /** @return array<string> */
    public static function getAvailableEnvironmentTypes(): array
    {
        return ['native', 'docker', 'docker-compose', 'ddev', 'hooli', 'warden'];
    }
}
