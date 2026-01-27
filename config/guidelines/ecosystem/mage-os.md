# Mage-OS Guidelines

## Overview

Mage-OS is a community-driven, independent distribution of Magento Open Source, maintained by the Mage-OS Association. It aims to ensure the long-term sustainability and community governance of the Magento ecosystem.

## Key Principles

| Principle | Description |
|-----------|-------------|
| Community Governed | Decisions made by community, not single vendor |
| Open Source | Fully open source with transparent development |
| Backward Compatible | Maintains compatibility with Magento modules |
| Performance Focused | Continuous performance improvements |
| Security First | Regular security updates and audits |

## Compatibility

Mage-OS is designed to be a drop-in replacement for Magento Open Source:

- Same module structure
- Same API contracts
- Same extension compatibility
- Same database schema

## Installation

```bash
# Create project with Mage-OS
composer create-project mage-os/project-community-edition .

# Or switch existing Magento installation
composer config repositories.mage-os composer https://repo.mage-os.org/
composer require mage-os/mage-os
```

## Version Alignment

| Mage-OS Version | Magento Version | PHP Support |
|-----------------|-----------------|-------------|
| 1.0.x | 2.4.6 | 8.1, 8.2 |
| 1.1.x | 2.4.7 | 8.1, 8.2, 8.3 |

## Community Packages

Mage-OS ecosystem includes community-maintained packages:

```bash
# Async operations
composer require mage-os/module-async-events

# Performance improvements
composer require mage-os/module-cache-warmer

# Developer tools
composer require --dev mage-os/module-developer-toolbar
```

## Contributing

### Repository Structure

```
github.com/mage-os/
├── mageos-magento2/          # Core distribution
├── mageos-inventory/         # MSI modules
├── mageos-page-builder/      # Page Builder
├── mirror-*                  # Upstream mirrors
└── github-actions/           # CI/CD
```

### Contribution Process

1. Fork the repository
2. Create feature branch
3. Make changes following coding standards
4. Submit pull request
5. Pass CI checks
6. Community review
7. Merge by maintainers

## Security

### Reporting

Security issues should be reported through:
- HackerOne program
- security@mage-os.org

### Update Process

```bash
# Check for updates
composer outdated mage-os/*

# Update Mage-OS packages
composer update mage-os/* --with-dependencies
```

## Module Development

Developing for Mage-OS follows the same patterns as Magento:

```php
<?php
declare(strict_types=1);

namespace Vendor\Module;

use Magento\Framework\Component\ComponentRegistrar;

// Same registration pattern
ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Vendor_Module',
    __DIR__
);
```

## Testing

Mage-OS encourages comprehensive testing:

```bash
# Run unit tests
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist

# Run integration tests
vendor/bin/phpunit -c dev/tests/integration/phpunit.xml

# Run static analysis
vendor/bin/phpstan analyse --level=8
```

## Performance Enhancements

Mage-OS includes performance optimizations:

1. **Optimized queries** - Reduced database load
2. **Improved caching** - Better cache utilization
3. **Async operations** - Non-blocking processes
4. **Code optimization** - Reduced overhead

## Best Practices

1. **Stay updated** - Follow Mage-OS releases
2. **Contribute back** - Report bugs, submit patches
3. **Test thoroughly** - Ensure compatibility
4. **Join community** - Discord, forums, meetings
5. **Follow standards** - PSR-12, Magento coding standards
6. **Use official channels** - For security reports
