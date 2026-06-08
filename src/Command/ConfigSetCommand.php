<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Command;

use Inchoo\MagentoBricklayer\Config\ConfigInitializer;
use Inchoo\MagentoBricklayer\Config\ConfigLoader;
use Inchoo\MagentoBricklayer\Config\ConfigValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Config Set Command
 *
 * Updates a single value in .bricklayer.json using dot-notation keys.
 */
#[AsCommand(
    name: 'config:set',
    description: 'Set a value in .bricklayer.json using dot-notation'
)]
class ConfigSetCommand extends AbstractBricklayerCommand
{
    private const CONFIG_FILE = '.bricklayer.json';

    /**
     * Schema of extra options per tool (beyond the universal `enabled` flag).
     * Keys are tool names; values list option specs with type/description/default.
     *
     * @var array<string, array<string, array{type: string, description: string, default: mixed}>>
     */
    private const TOOL_EXTRAS = [
        'code-runner' => [
            'allow_write' => [
                'type' => 'bool',
                'description' => 'Allow database writes (false = changes rolled back)',
                'default' => false,
            ],
            'max_timeout' => [
                'type' => 'int',
                'description' => 'Maximum execution timeout in seconds',
                'default' => 60,
            ],
        ],
        'database-query' => [
            'max_rows' => [
                'type' => 'int',
                'description' => 'Maximum rows returned per query',
                'default' => 100,
            ],
        ],
        'log' => [
            'max_lines' => [
                'type' => 'int',
                'description' => 'Maximum log lines returned per read',
                'default' => 500,
            ],
        ],
    ];

    protected function configure(): void
    {
        parent::configure();
        $this
            ->addArgument(
                'key',
                InputArgument::OPTIONAL,
                'Dot-notation key (e.g. "tools.product-delete.enabled"). If omitted, the command prompts you to pick one.'
            )
            ->addArgument(
                'value',
                InputArgument::OPTIONAL,
                'Value to set (true|false|null|integer|float|string|JSON). If omitted, the command prompts you.'
            )
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command updates a single value in <comment>.bricklayer.json</comment>.

<comment>Interactive mode</comment> (no arguments — walks you through the options):

  <info>%command.full_name%</info>

<comment>Scripted mode</comment> (positional arguments):

  <info>%command.full_name% tools.product-delete.enabled true</info>
  <info>%command.full_name% tools.database-query.max_rows 250</info>
  <info>%command.full_name% tools.code-runner.allow_write false</info>
  <info>%command.full_name% tools.log.max_lines 1000</info>

Values are parsed automatically:
  <comment>true</comment>/<comment>false</comment>    -> boolean
  <comment>null</comment>          -> null
  <comment>123</comment> / <comment>1.5</comment>  -> integer / float
  <comment>[...]</comment> / <comment>{...}</comment> -> JSON-decoded array/object
  (anything else) -> string

If <comment>.bricklayer.json</comment> does not exist, it is generated first with
deploy-mode-aware defaults before the value is applied.

Setting a value that the runtime does not actually honor (for example the
<comment>enabled</comment> flag on a read-only introspection tool) emits a warning but still
writes the file.
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $magentoRoot = $this->resolveMagentoRoot($input, $io);
        if ($magentoRoot === null) {
            return Command::FAILURE;
        }

        $configPath = $magentoRoot . '/' . self::CONFIG_FILE;

        if (!file_exists($configPath)) {
            (new ConfigInitializer())->generate($magentoRoot);
            $io->text('  <info>✓</info> Created ' . self::CONFIG_FILE . ' with defaults');
        }

        $config = $this->loadConfig($configPath, $io);
        if ($config === null) {
            return Command::FAILURE;
        }

        $key = $input->getArgument('key');
        $rawValue = $input->getArgument('value');

        if ($key === null || $rawValue === null) {
            if (!$input->isInteractive()) {
                $io->error(sprintf(
                    'Missing required argument: %s. Run without --no-interaction to get a guided prompt.',
                    $key === null ? 'key' : 'value'
                ));
                return Command::FAILURE;
            }

            if ($key === null) {
                $key = $this->selectKeyInteractively($io, $magentoRoot);
                if ($key === null) {
                    $io->warning('No selection made. Aborting.');
                    return Command::SUCCESS;
                }
            }

            if ($rawValue === null) {
                $rawValue = $this->promptForValueInteractively($io, $magentoRoot, $key);
                if ($rawValue === null) {
                    $io->warning('No value provided. Aborting.');
                    return Command::SUCCESS;
                }
            }
        }

        $key = (string) $key;
        $rawValue = (string) $rawValue;
        $value = $this->parseValue($rawValue);

        $oldExists = false;
        $oldValue = $this->getNested($config, $key, $oldExists);

        $config = $this->setNested($config, $key, $value);

        $validator = new ConfigValidator();
        if (!$validator->validate($config)) {
            $io->error('Resulting configuration is invalid — refusing to write:');
            foreach ($validator->getErrors() as $err) {
                $io->text('  - ' . $err);
            }
            return Command::FAILURE;
        }

        foreach ($validator->getWarnings() as $warn) {
            $io->warning($warn);
        }

        $this->warnIfIneffective($io, $key);

        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        if (file_put_contents($configPath, $json, LOCK_EX) === false) {
            $io->error("Failed to write {$configPath}");
            return Command::FAILURE;
        }

        $io->text(sprintf(
            '  <info>✓</info> Set <comment>%s</comment>: %s → %s',
            $key,
            $this->formatValue($oldValue, $oldExists),
            $this->formatValue($value, true)
        ));

        $this->verifyRoundTrip($io, $magentoRoot, $key, $value);
        $this->warnIfEnvVarShadowing($io, $key);

        $io->newLine();
        $io->note(
            'Changes apply on the next MCP tool call — bricklayer hot-reloads '
            . '.bricklayer.json automatically based on file mtime, so no agent '
            . 'restart is required. If a tool call is already in flight, it will '
            . 'finish with the old config; subsequent calls will see the new value.'
        );

        return Command::SUCCESS;
    }

    private function verifyRoundTrip(SymfonyStyle $io, string $magentoRoot, string $key, mixed $expected): void
    {
        try {
            $loader = new ConfigLoader();
            $loader->load($magentoRoot);
            $readBack = $loader->get($key);
        } catch (\Throwable $e) {
            $io->warning('Could not verify the change — ' . $e->getMessage());
            return;
        }

        if ($readBack === $expected) {
            $io->text('  <info>✓</info> Verified: value is readable via ConfigLoader');
            return;
        }

        $io->warning(sprintf(
            'Wrote %s but ConfigLoader reads back %s. The file change may be shadowed '
            . 'by an environment variable or a merge rule.',
            $this->formatValue($expected, true),
            $this->formatValue($readBack, true)
        ));
    }

    private function warnIfEnvVarShadowing(SymfonyStyle $io, string $key): void
    {
        $envKey = 'BRICKLAYER_' . strtoupper(str_replace(['.', '-'], '_', $key));
        if (getenv($envKey) === false) {
            return;
        }

        $io->warning(sprintf(
            "Environment variable %s is set and takes precedence over .bricklayer.json. "
            . "Unset it (unset %s) for the file value to take effect.",
            $envKey,
            $envKey
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadConfig(string $configPath, SymfonyStyle $io): ?array
    {
        $contents = @file_get_contents($configPath);
        if ($contents === false) {
            $io->error("Unable to read {$configPath}");
            return null;
        }

        $decoded = json_decode($contents, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            $io->error(sprintf(
                'Invalid JSON in %s: %s',
                $configPath,
                json_last_error_msg()
            ));
            return null;
        }

        return $decoded;
    }

    private function parseValue(string $raw): mixed
    {
        if ($raw === 'true') {
            return true;
        }
        if ($raw === 'false') {
            return false;
        }
        if ($raw === 'null') {
            return null;
        }
        if (is_numeric($raw)) {
            return str_contains($raw, '.') ? (float) $raw : (int) $raw;
        }
        if (str_starts_with($raw, '[') || str_starts_with($raw, '{')) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
        return $raw;
    }

    /**
     * @param array<string, mixed> $array
     */
    private function getNested(array $array, string $key, bool &$exists): mixed
    {
        $exists = true;
        $value = $array;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                $exists = false;
                return null;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    /**
     * @param array<string, mixed> $array
     * @return array<string, mixed>
     */
    private function setNested(array $array, string $key, mixed $value): array
    {
        $segments = explode('.', $key);
        $current = &$array;
        $last = count($segments) - 1;
        foreach ($segments as $i => $segment) {
            if ($i === $last) {
                $current[$segment] = $value;
                break;
            }
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }
        return $array;
    }

    private function warnIfIneffective(SymfonyStyle $io, string $key): void
    {
        if (!preg_match('/^tools\.([^.]+)\.enabled$/', $key, $matches)) {
            return;
        }

        $tool = $matches[1];
        $configurable = ConfigInitializer::discoverConfigurableTools();

        if (!in_array($tool, $configurable, true)) {
            $io->warning(sprintf(
                "Tool '%s' does not honor the 'enabled' flag at runtime — this setting will be ignored. "
                . "Read-only introspection tools cannot be disabled via configuration.",
                $tool
            ));
        }
    }

    private function formatValue(mixed $value, bool $exists): string
    {
        if (!$exists) {
            return '<comment>(unset)</comment>';
        }
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        return (string) json_encode($value);
    }

    private function selectKeyInteractively(SymfonyStyle $io, string $magentoRoot): ?string
    {
        $loader = $this->freshLoader($magentoRoot);

        $tools = ConfigInitializer::discoverConfigurableTools();
        if ($tools === []) {
            $io->error('No runtime-configurable tools discovered — cannot offer interactive selection.');
            return null;
        }

        $labels = [];
        foreach ($tools as $tool) {
            $parts = [];
            foreach ($this->getOptionsForTool($tool) as $option => $spec) {
                $current = $loader->get("tools.{$tool}.{$option}", $spec['default']);
                $parts[] = sprintf('%s=%s', $option, $this->formatScalar($current));
            }
            $labels[$tool] = sprintf('%s  (%s)', $tool, implode(', ', $parts));
        }

        $io->section('Select a tool to configure');
        $io->text(sprintf('%d runtime-configurable tools available.', count($tools)));

        $selectedLabel = $io->choice(
            'Tool',
            array_values($labels)
        );

        $selectedTool = array_flip($labels)[$selectedLabel] ?? null;
        if ($selectedTool === null) {
            return null;
        }

        $options = $this->getOptionsForTool($selectedTool);

        if (count($options) === 1) {
            $optionName = array_key_first($options);
        } else {
            $optionLabels = [];
            foreach ($options as $optName => $spec) {
                $current = $loader->get(
                    "tools.{$selectedTool}.{$optName}",
                    $spec['default']
                );
                $optionLabels[$optName] = sprintf(
                    '%s = %s  [%s] — %s',
                    $optName,
                    $this->formatScalar($current),
                    $spec['type'],
                    $spec['description']
                );
            }

            $io->section(sprintf('Select setting for "%s"', $selectedTool));
            $selectedOptLabel = $io->choice('Setting', array_values($optionLabels));
            $optionName = array_flip($optionLabels)[$selectedOptLabel] ?? null;
            if ($optionName === null) {
                return null;
            }
        }

        return "tools.{$selectedTool}.{$optionName}";
    }

    private function promptForValueInteractively(SymfonyStyle $io, string $magentoRoot, string $key): ?string
    {
        $loader = $this->freshLoader($magentoRoot);
        $spec = $this->getSpecForKey($key);
        $current = $loader->get($key, $spec['default'] ?? null);

        $io->section('Enter new value');
        $io->text([
            sprintf('Key:     <comment>%s</comment>', $key),
            sprintf('Current: %s', $this->formatScalar($current)),
            sprintf('Type:    %s', $spec['type'] ?? 'string'),
        ]);

        if ($spec !== null && $spec['type'] === 'bool') {
            $default = (bool) $current ? 'true' : 'false';
            return $io->choice('New value', ['false', 'true'], $default);
        }

        if ($spec !== null && $spec['type'] === 'int') {
            $default = $current !== null ? (string) $current : '';
            $answer = $io->ask('New value', $default === '' ? null : $default, function ($value) {
                if ($value === null || $value === '') {
                    throw new \RuntimeException('Value is required');
                }
                if (!is_numeric($value) || (int) $value != $value) {
                    throw new \RuntimeException("Value must be an integer, got: {$value}");
                }
                return (string) (int) $value;
            });
            return $answer;
        }

        $default = $current !== null && is_scalar($current) ? (string) $current : null;
        return $io->ask('New value', $default);
    }

    /**
     * @return array<string, array{type: string, description: string, default: mixed}>
     */
    private function getOptionsForTool(string $tool): array
    {
        $enabled = [
            'enabled' => [
                'type' => 'bool',
                'description' => 'Master enable/disable switch for this tool',
                'default' => true,
            ],
        ];
        return array_merge($enabled, self::TOOL_EXTRAS[$tool] ?? []);
    }

    /**
     * @return array{type: string, description: string, default: mixed}|null
     */
    private function getSpecForKey(string $key): ?array
    {
        if (!preg_match('/^tools\.([^.]+)\.([^.]+)$/', $key, $matches)) {
            return null;
        }
        [, $tool, $option] = $matches;
        return $this->getOptionsForTool($tool)[$option] ?? null;
    }

    private function freshLoader(string $magentoRoot): ConfigLoader
    {
        $loader = new ConfigLoader();
        try {
            $loader->load($magentoRoot);
        } catch (\Throwable) {
            // fall through; callers will get defaults via get($key, $default)
        }
        return $loader;
    }

    private function formatScalar(mixed $value): string
    {
        if ($value === null) {
            return '<comment>null</comment>';
        }
        if (is_bool($value)) {
            return $value ? '<info>true</info>' : '<info>false</info>';
        }
        if (is_scalar($value)) {
            return '<info>' . (string) $value . '</info>';
        }
        return (string) json_encode($value);
    }
}
