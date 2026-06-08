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
            'hooli' => $this->buildDockerComposeConfig('../docker-compose.yml', 'apache-php'),
            'warden' => [
                'command' => 'warden',
                'args' => ['shell', '-c', 'php vendor/bin/bricklayer-mcp'],
            ],
            'docker-compose' => $this->buildDockerComposeConfig(null, $this->detectPhpService()),
            'docker' => $this->buildDockerExecConfig($this->detectContainerName()),
            default => [
                'command' => 'php',
                'args' => ['vendor/bin/bricklayer-mcp'],
            ],
        };
    }

    /** @return array<string, mixed> */
    private function buildDockerComposeConfig(?string $composeFile, string $service): array
    {
        $args = ['compose'];
        if ($composeFile !== null) {
            array_push($args, '-f', $composeFile);
        }
        $args[] = 'exec';
        $args[] = '-T';

        $user = $this->detectContainerUser();
        if ($user !== null) {
            array_push($args, '-u', $user);
        }

        array_push($args, $service, 'php', 'vendor/bin/bricklayer-mcp');

        return ['command' => 'docker', 'args' => $args];
    }

    /** @return array<string, mixed> */
    private function buildDockerExecConfig(string $container): array
    {
        $args = ['exec', '-i'];

        $user = $this->detectContainerUser();
        if ($user !== null) {
            array_push($args, '-u', $user);
        }

        array_push($args, $container, 'php', 'vendor/bin/bricklayer-mcp');

        return ['command' => 'docker', 'args' => $args];
    }

    /**
     * Detect the non-root user that should run the MCP server inside the container.
     *
     * Resolution order:
     * 1. BRICKLAYER_CONTAINER_USER env var (explicit override)
     * 2. Owner of composer.json (the user who installed Magento)
     * 3. Hooli .env APACHE_USER value
     */
    private function detectContainerUser(): ?string
    {
        $envUser = getenv('BRICKLAYER_CONTAINER_USER');
        if ($envUser !== false && $envUser !== '') {
            return $envUser;
        }

        $markerFile = $this->projectRoot . '/composer.json';
        if (file_exists($markerFile) && function_exists('posix_getpwuid')) {
            $ownerUid = fileowner($markerFile);
            if ($ownerUid !== false && $ownerUid !== 0) {
                $ownerInfo = posix_getpwuid($ownerUid);
                if ($ownerInfo !== false) {
                    return $ownerInfo['name'];
                }
            }
        }

        $parentEnv = dirname($this->projectRoot) . '/.env';
        if (file_exists($parentEnv)) {
            $content = file_get_contents($parentEnv);
            if ($content !== false && preg_match('/^APACHE_USER=(.+)$/m', $content, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
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
        return array_keys(self::getEnvironmentTypeLabels());
    }

    /**
     * Returns the canonical env-type → human-readable label map.
     * This is the single authoritative source for both the set of supported types
     * and their display labels.
     *
     * @return array<string, string>
     */
    public static function getEnvironmentTypeLabels(): array
    {
        return [
            'native' => 'Native (no containers)',
            'ddev' => 'DDEV',
            'hooli' => 'Hooli',
            'warden' => 'Warden',
            'docker-compose' => 'Docker Compose',
            'docker' => 'Docker',
        ];
    }
}
