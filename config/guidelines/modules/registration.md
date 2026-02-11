# Module Registration Guidelines

## Overview

Every Magento 2 module must be registered with the ComponentRegistrar to be recognized by the system.

## registration.php

The `registration.php` file is located at the module root and is loaded by Composer's autoloader.

```php
<?php

declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Vendor_ModuleName',
    __DIR__
);
```

## Key Points

1. **File must exist** - Required for module recognition
2. **Module name format** - `Vendor_ModuleName` (PascalCase)
3. **Matches module.xml** - Name must match `etc/module.xml` declaration
4. **Loaded via Composer** - Add to `autoload.files` in `composer.json`

## Component Types

| Type | Constant | Example |
|------|----------|---------|
| Module | `ComponentRegistrar::MODULE` | `Vendor_Module` |
| Theme | `ComponentRegistrar::THEME` | `frontend/Vendor/theme` |
| Language | `ComponentRegistrar::LANGUAGE` | `vendor_language` |
| Library | `ComponentRegistrar::LIBRARY` | `vendor/library` |

## composer.json Integration

```json
{
    "name": "vendor/module-name",
    "type": "magento2-module",
    "autoload": {
        "files": ["registration.php"],
        "psr-4": {
            "Vendor\\ModuleName\\": ""
        }
    }
}
```

## Best Practices

1. **Always use `declare(strict_types=1)`**
2. **Use `__DIR__` for path** - Not hardcoded paths
3. **Match naming conventions** - Vendor_Module format
4. **Include in version control** - Essential module file
