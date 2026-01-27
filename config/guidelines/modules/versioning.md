# Module Versioning Guidelines

## Overview

Module versioning follows Semantic Versioning (SemVer) and determines database schema and data migrations.

## Version Declaration

### etc/module.xml

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">
    <module name="Vendor_Module"/>
</config>
```

Note: Version is now managed by `composer.json`, not `setup_version` attribute.

### composer.json

```json
{
    "name": "vendor/module-name",
    "version": "1.2.3",
    "type": "magento2-module"
}
```

## Semantic Versioning

Format: `MAJOR.MINOR.PATCH`

| Component | When to Increment |
|-----------|-------------------|
| MAJOR | Breaking changes, incompatible API changes |
| MINOR | New features, backward compatible |
| PATCH | Bug fixes, backward compatible |

## Version Examples

| Version | Change Type |
|---------|-------------|
| 1.0.0 | Initial release |
| 1.0.1 | Bug fix |
| 1.1.0 | New feature added |
| 2.0.0 | Breaking API change |

## Schema and Data Patches

Patches replace the old InstallSchema/UpgradeSchema system.

### Schema Patch

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Setup\Patch\Schema;

use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class AddColumn implements SchemaPatchInterface
{
    public function __construct(
        private readonly SchemaSetupInterface $schemaSetup
    ) {
    }

    public function apply(): self
    {
        $this->schemaSetup->startSetup();
        // Schema changes
        $this->schemaSetup->endSetup();
        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
```

### Data Patch

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class AddData implements DataPatchInterface, PatchVersionInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();
        // Data changes
        $this->moduleDataSetup->endSetup();
        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }

    public static function getVersion(): string
    {
        return '1.0.1';
    }
}
```

## Best Practices

1. **Use SemVer strictly** - Clear version communication
2. **Document changes** - Maintain CHANGELOG
3. **Test upgrades** - Verify migrations work both ways
4. **Avoid breaking changes** - Deprecate before removing
5. **Use patches** - Not InstallSchema/UpgradeSchema (deprecated)
