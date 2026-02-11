# Cron Development Skill

## Overview

Cron jobs in Magento 2 execute scheduled tasks like indexing, email sending, and data cleanup. This skill covers creating, configuring, and debugging cron jobs.

## Key Components

| Component | Description |
|-----------|-------------|
| Cron Class | PHP class with execute() method |
| crontab.xml | Schedule configuration |
| cron_groups.xml | Group configuration |

## Creating a Cron Job

### 1. Cron Class

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Cron;

use Psr\Log\LoggerInterface;
use Vendor\Module\Api\ProcessorInterface;

class ProcessData
{
    /**
     * @param LoggerInterface $logger
     * @param ProcessorInterface $processor
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ProcessorInterface $processor
    ) {
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        $this->logger->info('Cron job ProcessData started');

        try {
            $processed = $this->processor->process();
            $this->logger->info('Cron job ProcessData completed', [
                'processed_count' => $processed
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Cron job ProcessData failed', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
```

### 2. Schedule Configuration (crontab.xml)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Cron:etc/crontab.xsd">

    <group id="default">
        <!-- Fixed schedule -->
        <job name="vendor_module_process_data"
             instance="Vendor\Module\Cron\ProcessData"
             method="execute">
            <schedule>0 * * * *</schedule>
        </job>

        <!-- Configurable schedule -->
        <job name="vendor_module_cleanup"
             instance="Vendor\Module\Cron\Cleanup"
             method="execute">
            <config_path>vendor_module/cron/cleanup_schedule</config_path>
        </job>
    </group>

    <!-- Custom group -->
    <group id="vendor_module">
        <job name="vendor_module_sync"
             instance="Vendor\Module\Cron\SyncData"
             method="execute">
            <schedule>*/15 * * * *</schedule>
        </job>
    </group>

</config>
```

### 3. Cron Group Configuration (cron_groups.xml)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Cron:etc/cron_groups.xsd">
    <group id="vendor_module">
        <schedule_generate_every>1</schedule_generate_every>
        <schedule_ahead_for>4</schedule_ahead_for>
        <schedule_lifetime>15</schedule_lifetime>
        <history_cleanup_every>10</history_cleanup_every>
        <history_success_lifetime>60</history_success_lifetime>
        <history_failure_lifetime>600</history_failure_lifetime>
        <use_separate_process>0</use_separate_process>
    </group>
</config>
```

## Cron Schedule Syntax

```
* * * * *
│ │ │ │ │
│ │ │ │ └── Day of week (0-7, Sunday = 0 or 7)
│ │ │ └──── Month (1-12)
│ │ └────── Day of month (1-31)
│ └──────── Hour (0-23)
└────────── Minute (0-59)
```

### Common Schedules

| Schedule | Meaning |
|----------|---------|
| `* * * * *` | Every minute |
| `*/5 * * * *` | Every 5 minutes |
| `0 * * * *` | Every hour |
| `0 0 * * *` | Daily at midnight |
| `0 0 * * 0` | Weekly on Sunday |
| `0 0 1 * *` | Monthly on the 1st |
| `0 2 * * *` | Daily at 2:00 AM |
| `30 4 * * 1-5` | Weekdays at 4:30 AM |

## Configurable Schedule

### System Configuration (system.xml)

```xml
<field id="cleanup_schedule" translate="label comment" type="text" sortOrder="20" showInDefault="1" showInWebsite="0" showInStore="0">
    <label>Cleanup Schedule</label>
    <comment>Cron expression (e.g., 0 2 * * *)</comment>
</field>
```

### Default Configuration (config.xml)

```xml
<default>
    <vendor_module>
        <cron>
            <cleanup_schedule>0 2 * * *</cleanup_schedule>
        </cron>
    </vendor_module>
</default>
```

## Advanced Cron Class

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Cron;

use Psr\Log\LoggerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Vendor\Module\Model\ResourceModel\Item\CollectionFactory;

class CleanupOldRecords
{
    private const string CONFIG_ENABLED = 'vendor_module/cleanup/enabled';
    private const string CONFIG_DAYS = 'vendor_module/cleanup/days_to_keep';

    /**
     * @param LoggerInterface $logger
     * @param ScopeConfigInterface $scopeConfig
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        if (!$this->isEnabled()) {
            $this->logger->debug('Cleanup cron disabled');
            return;
        }

        $startTime = microtime(true);
        $this->logger->info('Cleanup cron started');

        try {
            $deleted = $this->cleanupOldRecords();

            $duration = round(microtime(true) - $startTime, 2);
            $this->logger->info('Cleanup cron completed', [
                'deleted_count' => $deleted,
                'duration_seconds' => $duration
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Cleanup cron failed: ' . $e->getMessage());
            throw $e; // Re-throw to mark job as failed
        }
    }

    /**
     * @return bool
     */
    private function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::CONFIG_ENABLED);
    }

    /**
     * @return int
     */
    private function getDaysToKeep(): int
    {
        return (int) $this->scopeConfig->getValue(self::CONFIG_DAYS) ?: 30;
    }

    /**
     * @return int
     */
    private function cleanupOldRecords(): int
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$this->getDaysToKeep()} days"));

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('created_at', ['lt' => $cutoffDate]);

        $count = $collection->getSize();

        foreach ($collection as $item) {
            $item->delete();
        }

        return $count;
    }
}
```

## Running Cron

### Command Line

```bash
# Run all cron jobs
bin/magento cron:run

# Run specific group
bin/magento cron:run --group=vendor_module

# Run a single job (for testing)
bin/magento cron:run --group=default --job=vendor_module_process_data
```

### Crontab Setup

```bash
# Edit crontab
crontab -e

# Add Magento cron
* * * * * cd /var/www/magento && php bin/magento cron:run >> /var/log/magento.cron.log 2>&1
```

## Debugging

### Check Cron Schedule

```sql
SELECT * FROM cron_schedule
WHERE job_code = 'vendor_module_process_data'
ORDER BY scheduled_at DESC
LIMIT 10;
```

### Check Cron History

```sql
SELECT job_code, status, COUNT(*) as count
FROM cron_schedule
WHERE scheduled_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY job_code, status;
```

### Status Values

| Status | Meaning |
|--------|---------|
| pending | Waiting to run |
| running | Currently executing |
| success | Completed successfully |
| missed | Missed execution window |
| error | Failed with error |

## Best Practices

1. **Log execution** - Start, end, and errors
2. **Check if enabled** - Allow disabling via config
3. **Handle exceptions** - Log and re-throw to mark as failed
4. **Use transactions** - For data modifications
5. **Implement idempotency** - Safe to run multiple times
6. **Monitor execution time** - Log duration
7. **Clean up history** - Configure history_success_lifetime
8. **Use separate groups** - For isolation and parallel execution
9. **Test thoroughly** - Run manually before deploying
