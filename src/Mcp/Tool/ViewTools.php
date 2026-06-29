<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Tool;

use Inchoo\MagentoBricklayer\Bootstrap\AreaEmulator;
use Inchoo\MagentoBricklayer\Bootstrap\MagentoBootstrap;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\ChecksConfig;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RequiresMagento;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RequiresValidVerbosity;
use Inchoo\MagentoBricklayer\Mcp\Tool\Concern\RespondsWithErrors;
use Mcp\Capability\Attribute\McpTool;

/**
 * Runtime introspection of the Magento view layer: resolved layout handles and
 * merged admin UI component configuration. Both surface cross-module/theme merged
 * state that cannot be read from any single source file.
 */
class ViewTools
{
    use ChecksConfig;
    use RequiresMagento;
    use RequiresValidVerbosity;
    use RespondsWithErrors;

    private const VALID_AREAS = [
        AreaEmulator::AREA_FRONTEND,
        AreaEmulator::AREA_ADMINHTML,
    ];

    /** Guards against pathological layouts producing an unbounded payload. */
    private const MAX_NODES = 4000;
    private const MAX_DEPTH = 40;

    /** Structural directives tallied in directive_summary (block config tags are excluded as noise). */
    private const DIRECTIVE_TAGS = [
        'block',
        'container',
        'referenceBlock',
        'referenceContainer',
        'move',
        'remove',
        'uiComponent',
    ];

    private ?AreaEmulator $areaEmulator = null;

    /**
     * Inspect a Magento layout handle's runtime-merged structure, or list registered handles.
     *
     * @param string $handle    Layout handle to resolve (omit to list all registered handles).
     * @param string $area      Area to resolve in: frontend or adminhtml.
     * @param string $verbosity minimal | standard | detailed
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'layout-inspect',
        description: 'Inspect a Magento layout handle\'s runtime-merged block/container tree '
            . '(merged across all modules and the active theme), or omit the handle to list all '
            . 'registered handles for an area. Shows applied referenceBlock/referenceContainer/'
            . 'move/remove directives, declared and theme-resolved .phtml template paths, and the '
            . 'page layout. Params: handle, area (frontend|adminhtml), verbosity.',
        meta: ['hidden' => true]
    )]
    public function inspectLayout(string $handle = '', string $area = 'frontend', string $verbosity = 'standard'): array
    {
        if ($error = $this->requireValidVerbosity($verbosity)) {
            return $error;
        }

        if (!in_array($area, self::VALID_AREAS, true)) {
            return $this->errorResponse(
                sprintf('Invalid area "%s". Allowed: %s', $area, implode(', ', self::VALID_AREAS))
            );
        }

        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($error = $this->requireToolEnabled('layout-inspect')) {
            return $error;
        }

        return $this->runGuarded(function () use ($handle, $area, $verbosity) {
            return $this->emulateView($area, function () use ($handle, $area, $verbosity) {
                return $handle === ''
                    ? $this->listHandles($area)
                    : $this->resolveHandle($handle, $area, $verbosity);
            });
        });
    }

    /**
     * Inspect an admin UI component's runtime-merged configuration (grid or form).
     *
     * @param string $name      UI component name, e.g. customer_listing.
     * @param string $verbosity minimal | standard | detailed
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'ui-component-inspect',
        description: 'Inspect an admin UI component\'s runtime-merged configuration (grid or form), '
            . 'merged across all modules. Shows the resolved component tree, data source, '
            . 'columns/fieldsets and child components. Params: name (required, e.g. customer_listing), '
            . 'verbosity (minimal|standard|detailed).',
        meta: ['hidden' => true]
    )]
    public function inspectUiComponent(string $name = '', string $verbosity = 'standard'): array
    {
        if ($error = $this->requireValidVerbosity($verbosity)) {
            return $error;
        }

        if (trim($name) === '') {
            return $this->errorResponse('name is required (the UI component name, e.g. customer_listing).');
        }

        if ($error = $this->requireMagento()) {
            return $error;
        }

        if ($error = $this->requireToolEnabled('ui-component-inspect')) {
            return $error;
        }

        return $this->runGuarded(function () use ($name, $verbosity) {
            return $this->getAreaEmulator()->emulateAreaCode(
                AreaEmulator::AREA_ADMINHTML,
                fn() => $this->resolveUiComponent(trim($name), $verbosity)
            );
        });
    }

    /**
     * List the layout handles registered for the emulated area (replaces the static
     * layouts_reference resource — the runtime list cannot drift from the code).
     *
     * @return array<string, mixed>
     */
    private function listHandles(string $area): array
    {
        $layout = MagentoBootstrap::create(\Magento\Framework\View\LayoutInterface::class);
        /** @var \Magento\Framework\View\Model\Layout\Merge $update */
        $update = $layout->getUpdate();

        $handles = array_values(array_unique($update->getAvailableHandles()));
        sort($handles);

        return [
            'mode' => 'list',
            'area' => $area,
            'theme' => $this->themeCode($update),
            'total' => count($handles),
            'handles' => $handles,
            'note' => 'Pass a handle to resolve its merged block/container tree.',
            '_skill_hint' => 'For layout XML patterns: development-context category=frontend',
        ];
    }

    /**
     * Resolve a single handle into its merged structure tree.
     *
     * @return array<string, mixed>
     */
    private function resolveHandle(string $handle, string $area, string $verbosity): array
    {
        $layout = MagentoBootstrap::create(\Magento\Framework\View\LayoutInterface::class);
        /** @var \Magento\Framework\View\Model\Layout\Merge $update */
        $update = $layout->getUpdate();
        $update->addHandle($handle);
        $update->load();

        $xml = $update->asSimplexml();
        if ($xml === null || $xml->count() === 0) {
            return $this->errorResponse(
                sprintf(
                    'Layout handle "%s" produced no merged layout in area "%s". '
                    . 'Omit the handle to list registered handles.',
                    $handle,
                    $area
                )
            );
        }

        // Template provenance is resolved through the active theme fallback (skipped for minimal).
        $fileSystem = $verbosity === 'minimal'
            ? null
            : MagentoBootstrap::get(\Magento\Framework\View\FileSystem::class);

        $nodeCount = 0;
        $directiveCounts = [];

        // asSimplexml() returns the merged update set as many sibling <body> blocks
        // (one per contributing layout file / handle), not a single merged <body>.
        // Walk every <body> so the structure reflects the full cross-module merge.
        $body = [];
        foreach ($xml->body as $bodyElement) {
            foreach ($bodyElement->children() as $child) {
                $body[] = $this->parseNode($child, $verbosity, $fileSystem, $nodeCount, $directiveCounts, 0);
                if ($nodeCount >= self::MAX_NODES) {
                    break 2;
                }
            }
        }

        $result = [
            'mode' => 'resolve',
            'handle' => $handle,
            'area' => $area,
            'theme' => $this->themeCode($update),
            'page_layout' => $update->getPageLayout() ?: null,
            'included_handles' => $this->includedHandles($xml),
            'directive_summary' => $directiveCounts,
            'structure' => $body,
        ];

        if ($nodeCount >= self::MAX_NODES) {
            $result['truncated'] = sprintf('Structure truncated at %d nodes.', self::MAX_NODES);
        }

        if ($verbosity === 'detailed') {
            $result['head'] = $this->parseHead($xml);
        }

        $result['_skill_hint'] = 'For layout XML patterns: development-context category=frontend';

        return $result;
    }

    /**
     * Recursively transform a merged-layout XML element into a structure node.
     *
     * @param array<string, int> $directiveCounts
     * @return array<string, mixed>
     */
    private function parseNode(
        \SimpleXMLElement $element,
        string $verbosity,
        ?\Magento\Framework\View\FileSystem $fileSystem,
        int &$nodeCount,
        array &$directiveCounts,
        int $depth
    ): array {
        $nodeCount++;

        $tag = $element->getName();
        if (in_array($tag, self::DIRECTIVE_TAGS, true)) {
            $directiveCounts[$tag] = ($directiveCounts[$tag] ?? 0) + 1;
        }

        $node = ['tag' => $tag];
        foreach ($element->attributes() as $key => $value) {
            $node[(string) $key] = (string) $value;
        }

        if ($fileSystem !== null && !empty($node['template'])) {
            $resolved = $this->resolveTemplate($fileSystem, $node['template']);
            if ($resolved !== null) {
                $node['resolved_template'] = $resolved;
            }
        }

        if ($nodeCount >= self::MAX_NODES || $depth >= self::MAX_DEPTH) {
            if ($element->count() > 0) {
                $node['_truncated'] = true;
            }
            return $node;
        }

        $children = [];
        foreach ($element->children() as $child) {
            // <arguments> trees are verbose block config; summarise unless detailed.
            if ($child->getName() === 'arguments' && $verbosity !== 'detailed') {
                $node['has_arguments'] = true;
                continue;
            }

            $children[] = $this->parseNode($child, $verbosity, $fileSystem, $nodeCount, $directiveCounts, $depth + 1);

            if ($nodeCount >= self::MAX_NODES) {
                break;
            }
        }

        if ($children !== []) {
            $node['children'] = $children;
        }

        return $node;
    }

    /**
     * Resolve a UI component name into its merged configuration tree.
     *
     * @return array<string, mixed>
     */
    private function resolveUiComponent(string $name, string $verbosity): array
    {
        $manager = MagentoBootstrap::create(\Magento\Ui\Model\Manager::class);
        $manager->prepareData($name);
        $data = $manager->getData($name);

        if ($data === []) {
            return $this->errorResponse(
                sprintf('UI component "%s" not found or produced no merged configuration.', $name)
            );
        }

        $root = $data[$name] ?? reset($data);
        if (!is_array($root)) {
            return $this->errorResponse(sprintf('UI component "%s" has an unexpected configuration shape.', $name));
        }

        $tree = $this->parseUiNode($name, $root, $verbosity, 0);

        $result = [
            'name' => $name,
            'type' => $root['attributes']['class'] ?? null,
            'component' => $root['attributes']['component'] ?? null,
            'data_source' => $this->findDataSource($root),
            'children' => $tree['children'] ?? [],
        ];

        if ($verbosity === 'detailed') {
            $result['arguments'] = $root['arguments'] ?? null;
        }

        $result['_skill_hint'] = 'For admin grid/form patterns: development-context category=ui-component';

        return $result;
    }

    /**
     * Recursively normalise a UI component config node.
     *
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private function parseUiNode(string $name, array $node, string $verbosity, int $depth): array
    {
        $attributes = is_array($node['attributes'] ?? null) ? $node['attributes'] : [];

        $result = [
            'name' => $attributes['name'] ?? $name,
            'type' => $attributes['class'] ?? null,
            'component' => $attributes['component'] ?? null,
        ];

        if ($verbosity === 'detailed' && !empty($node['arguments'])) {
            $result['arguments'] = $node['arguments'];
        }

        $childNodes = is_array($node['children'] ?? null) ? $node['children'] : [];

        if ($verbosity === 'minimal') {
            $result['children'] = array_keys($childNodes);
            return $result;
        }

        if ($depth >= self::MAX_DEPTH) {
            if ($childNodes !== []) {
                $result['_truncated'] = true;
            }
            return $result;
        }

        $children = [];
        foreach ($childNodes as $childName => $childNode) {
            if (is_array($childNode)) {
                $children[] = $this->parseUiNode((string) $childName, $childNode, $verbosity, $depth + 1);
            }
        }

        if ($children !== []) {
            $result['children'] = $children;
        }

        return $result;
    }

    /**
     * Locate the data-source child of a UI component config tree.
     *
     * @param array<string, mixed> $root
     * @return array<string, mixed>|null
     */
    private function findDataSource(array $root): ?array
    {
        foreach (($root['children'] ?? []) as $childName => $child) {
            if (!is_array($child)) {
                continue;
            }
            $class = $child['attributes']['class'] ?? '';
            if (str_contains((string) $childName, 'data_source') || str_contains((string) $class, 'DataSource')) {
                $args = $child['arguments']['data']['config'] ?? [];
                return [
                    'name' => (string) $childName,
                    'class' => $class ?: null,
                    'provider' => is_array($args) ? ($args['provider'] ?? null) : null,
                ];
            }
        }

        return null;
    }

    /**
     * Resolve a logical template path (Vendor_Module::path.phtml) to its theme-fallback
     * physical file, relativised against the Magento root.
     */
    private function resolveTemplate(\Magento\Framework\View\FileSystem $fileSystem, string $template): ?string
    {
        try {
            $resolved = $fileSystem->getTemplateFileName($template);
        } catch (\Throwable) {
            return null;
        }

        if (!is_string($resolved) || $resolved === '') {
            return null;
        }

        $root = MagentoBootstrap::getMagentoRoot();
        if ($root !== null && str_starts_with($resolved, $root)) {
            return ltrim(substr($resolved, strlen($root)), '/');
        }

        return $resolved;
    }

    /**
     * Extract the <update handle="..."/> includes that contributed to the merged layout.
     *
     * @return list<string>
     */
    private function includedHandles(\SimpleXMLElement $xml): array
    {
        $handles = [];
        foreach ($xml->update as $update) {
            $handle = (string) ($update['handle'] ?? '');
            if ($handle !== '') {
                $handles[] = $handle;
            }
        }

        return array_values(array_unique($handles));
    }

    /**
     * Summarise the resolved <head> directives across every merged <head> block.
     *
     * @return list<array<string, mixed>>
     */
    private function parseHead(\SimpleXMLElement $xml): array
    {
        $items = [];
        foreach ($xml->head as $head) {
            foreach ($head->children() as $element) {
                $entry = ['tag' => $element->getName()];
                foreach ($element->attributes() as $key => $value) {
                    $entry[(string) $key] = (string) $value;
                }
                $value = trim((string) $element);
                if ($value !== '') {
                    $entry['value'] = $value;
                }
                $items[] = $entry;
            }
        }

        return $items;
    }

    private function themeCode(object $update): ?string
    {
        if (!method_exists($update, 'getTheme')) {
            return null;
        }

        $theme = $update->getTheme();

        return $theme !== null ? $theme->getCode() : null;
    }

    /**
     * Run $callback with both the area code and the store design environment emulated,
     * unwinding both on exit. The design environment (theme/locale) is required for layout
     * merging and theme template fallback to resolve against the correct area's theme.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function emulateView(string $area, callable $callback): mixed
    {
        return $this->getAreaEmulator()->emulateAreaCode($area, function () use ($area, $callback) {
            $emulation = MagentoBootstrap::get(\Magento\Store\Model\App\Emulation::class);
            $emulation->startEnvironmentEmulation($this->emulationStoreId($area), $area, true);

            try {
                return $callback();
            } finally {
                $emulation->stopEnvironmentEmulation();
            }
        });
    }

    private function emulationStoreId(string $area): int
    {
        if ($area === AreaEmulator::AREA_ADMINHTML) {
            return 0;
        }

        try {
            $storeManager = MagentoBootstrap::get(\Magento\Store\Model\StoreManagerInterface::class);
            $store = $storeManager->getDefaultStoreView();
            if ($store !== null) {
                return (int) $store->getId();
            }
        } catch (\Throwable) {
            // Fall through to the default store id.
        }

        return 1;
    }

    private function getAreaEmulator(): AreaEmulator
    {
        return $this->areaEmulator ??= new AreaEmulator();
    }
}
