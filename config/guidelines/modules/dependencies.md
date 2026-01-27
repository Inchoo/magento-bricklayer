# Module Dependencies Guidelines

## Overview

Module dependencies ensure modules load in the correct order and have access to required functionality.

## Declaring Dependencies

### etc/module.xml

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">
    <module name="Vendor_Module">
        <sequence>
            <module name="Magento_Catalog"/>
            <module name="Magento_Customer"/>
        </sequence>
    </module>
</config>
```

### composer.json

```json
{
    "require": {
        "magento/module-catalog": "*",
        "magento/module-customer": "*"
    },
    "suggest": {
        "magento/module-wishlist": "For wishlist integration"
    }
}
```

## Dependency Types

| Type | Purpose | Where Declared |
|------|---------|----------------|
| Hard | Required for functionality | `require` in composer.json |
| Soft | Optional enhancement | `suggest` in composer.json |
| Sequence | Load order control | `<sequence>` in module.xml |

## Sequence vs Require

- **Sequence** - Only affects load order, doesn't enforce installation
- **Require** - Enforces module must be installed

## When to Add Dependencies

Add dependency when your module:
1. Uses classes from another module
2. Extends/implements interfaces from another module
3. Modifies database tables defined by another module
4. Requires configuration from another module

## Checking Dependencies

```bash
# List module dependencies
bin/magento module:status Vendor_Module

# Check circular dependencies
bin/magento setup:di:compile
```

## Best Practices

1. **Minimize dependencies** - Only add what's truly needed
2. **Use interfaces** - Depend on API modules when possible
3. **Avoid circular dependencies** - Module A depends on B depends on A
4. **Soft dependencies for optional features** - Use `suggest`
5. **Document dependencies** - Explain why each is needed
