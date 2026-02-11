# Message Queue Skill

## Overview

Magento's message queue system enables asynchronous operations by decoupling publishers from consumers. This skill covers implementing message queues using MySQL, RabbitMQ, or AWS SQS for background processing, improved performance, and scalable architectures.

## Queue Backends

| Backend | Use Case | Performance |
|---------|----------|-------------|
| MySQL | Development, simple deployments | Low-Medium |
| RabbitMQ | Production, high volume | High |
| AWS SQS | Cloud deployments | High |

## Architecture

```
Publisher → Exchange → Queue → Consumer
    ↓          ↓         ↓         ↓
  Message   Routing   Storage   Processing
```

## Creating a Custom Queue

### 1. Module Structure

```
app/code/Vendor/Queue/
├── Api/
│   └── Data/
│       └── MessageInterface.php
├── Model/
│   ├── Consumer.php
│   └── Publisher.php
├── etc/
│   ├── communication.xml
│   ├── queue_consumer.xml
│   ├── queue_publisher.xml
│   ├── queue_topology.xml
│   └── module.xml
└── registration.php
```

### 2. Define the Topic (etc/communication.xml)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Communication/etc/communication.xsd">

    <topic name="vendor.queue.custom.operation"
           request="Vendor\Queue\Api\Data\MessageInterface">
        <handler name="customOperationHandler"
                 type="Vendor\Queue\Model\Consumer"
                 method="process"/>
    </topic>

    <!-- Synchronous topic (for comparison) -->
    <topic name="vendor.queue.sync.operation"
           request="string"
           response="string">
        <handler name="syncHandler"
                 type="Vendor\Queue\Model\SyncHandler"
                 method="execute"/>
    </topic>

</config>
```

### 3. Configure Queue Topology (etc/queue_topology.xml)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework-message-queue:etc/topology.xsd">

    <!-- Exchange (for RabbitMQ) -->
    <exchange name="vendor.exchange"
              type="topic"
              connection="amqp">
        <binding id="vendorCustomBinding"
                 topic="vendor.queue.custom.operation"
                 destinationType="queue"
                 destination="vendor.queue.custom"/>
    </exchange>

    <!-- For MySQL, binding is simpler -->
    <exchange name="magento-db"
              type="topic"
              connection="db">
        <binding id="vendorDbBinding"
                 topic="vendor.queue.custom.operation"
                 destinationType="queue"
                 destination="vendor.queue.custom"/>
    </exchange>

</config>
```

### 4. Define Publisher (etc/queue_publisher.xml)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework-message-queue:etc/publisher.xsd">

    <publisher topic="vendor.queue.custom.operation">
        <connection name="amqp" exchange="vendor.exchange"/>
        <!-- Fallback to DB if RabbitMQ unavailable -->
        <connection name="db" exchange="magento-db" disabled="false"/>
    </publisher>

</config>
```

### 5. Define Consumer (etc/queue_consumer.xml)

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework-message-queue:etc/consumer.xsd">

    <consumer name="vendor.custom.consumer"
              queue="vendor.queue.custom"
              connection="amqp"
              handler="Vendor\Queue\Model\Consumer::process"
              maxMessages="100"
              maxIdleTime="60"/>

    <!-- Batch consumer for high-volume processing -->
    <consumer name="vendor.batch.consumer"
              queue="vendor.queue.batch"
              connection="amqp"
              consumerInstance="Magento\Framework\MessageQueue\BatchConsumer"
              handler="Vendor\Queue\Model\BatchConsumer::processBatch"
              maxMessages="1000"/>

</config>
```

### 6. Message Interface

```php
<?php

declare(strict_types=1);

namespace Vendor\Queue\Api\Data;

interface MessageInterface
{
    public const ENTITY_ID = 'entity_id';
    public const OPERATION = 'operation';
    public const DATA = 'data';
    public const CREATED_AT = 'created_at';

    /**
     * @return int|null
     */
    public function getEntityId(): ?int;

    /**
     * @param int $entityId
     * @return self
     */
    public function setEntityId(int $entityId): self;

    /**
     * @return string
     */
    public function getOperation(): string;

    /**
     * @param string $operation
     * @return self
     */
    public function setOperation(string $operation): self;

    /**
     * @return array
     */
    public function getData(): array;

    /**
     * @param array $data
     * @return self
     */
    public function setData(array $data): self;

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * @param string $createdAt
     * @return self
     */
    public function setCreatedAt(string $createdAt): self;
}
```

### 7. Message Implementation

```php
<?php

declare(strict_types=1);

namespace Vendor\Queue\Model;

use Vendor\Queue\Api\Data\MessageInterface;

class Message implements MessageInterface
{
    /**
     * @var int|null
     */
    private ?int $entityId = null;

    /**
     * @var string
     */
    private string $operation = '';

    /**
     * @var array
     */
    private array $data = [];

    /**
     * @var string|null
     */
    private ?string $createdAt = null;

    /**
     * @return int|null
     */
    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    /**
     * @param int $entityId
     * @return MessageInterface
     */
    public function setEntityId(int $entityId): MessageInterface
    {
        $this->entityId = $entityId;
        return $this;
    }

    /**
     * @return string
     */
    public function getOperation(): string
    {
        return $this->operation;
    }

    /**
     * @param string $operation
     * @return MessageInterface
     */
    public function setOperation(string $operation): MessageInterface
    {
        $this->operation = $operation;
        return $this;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array $data
     * @return MessageInterface
     */
    public function setData(array $data): MessageInterface
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    /**
     * @param string $createdAt
     * @return MessageInterface
     */
    public function setCreatedAt(string $createdAt): MessageInterface
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
```

### 8. Publisher Class

```php
<?php

declare(strict_types=1);

namespace Vendor\Queue\Model;

use Magento\Framework\MessageQueue\PublisherInterface;
use Vendor\Queue\Api\Data\MessageInterface;
use Vendor\Queue\Api\Data\MessageInterfaceFactory;
use Psr\Log\LoggerInterface;

class Publisher
{
    private const TOPIC_NAME = 'vendor.queue.custom.operation';

    /**
     * @param PublisherInterface $publisher
     * @param MessageInterfaceFactory $messageFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly PublisherInterface $publisher,
        private readonly MessageInterfaceFactory $messageFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param int $entityId
     * @param string $operation
     * @param array $data
     * @return void
     */
    public function publish(int $entityId, string $operation, array $data = []): void
    {
        try {
            $message = $this->messageFactory->create();
            $message->setEntityId($entityId)
                ->setOperation($operation)
                ->setData($data)
                ->setCreatedAt(date('Y-m-d H:i:s'));

            $this->publisher->publish(self::TOPIC_NAME, $message);

            $this->logger->info('Message published', [
                'topic' => self::TOPIC_NAME,
                'entity_id' => $entityId,
                'operation' => $operation,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to publish message', [
                'error' => $e->getMessage(),
                'entity_id' => $entityId,
            ]);
            throw $e;
        }
    }

    /**
     * @param array $items
     * @param string $operation
     * @return void
     */
    public function publishBulk(array $items, string $operation): void
    {
        foreach ($items as $item) {
            $this->publish($item['entity_id'], $operation, $item['data'] ?? []);
        }
    }
}
```

### 9. Consumer Class

```php
<?php

declare(strict_types=1);

namespace Vendor\Queue\Model;

use Vendor\Queue\Api\Data\MessageInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Exception\LocalizedException;

class Consumer
{
    /**
     * @param LoggerInterface $logger
     * @param OperationProcessor $processor
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly OperationProcessor $processor
    ) {
    }

    /**
     * @param MessageInterface $message
     * @return void
     */
    public function process(MessageInterface $message): void
    {
        $entityId = $message->getEntityId();
        $operation = $message->getOperation();

        $this->logger->info('Processing message', [
            'entity_id' => $entityId,
            'operation' => $operation,
        ]);

        try {
            $this->processor->execute($entityId, $operation, $message->getData());

            $this->logger->info('Message processed successfully', [
                'entity_id' => $entityId,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Message processing failed', [
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);

            // Throw to trigger retry mechanism
            throw new LocalizedException(__('Processing failed: %1', $e->getMessage()));
        }
    }
}
```

## Batch Consumer

```php
<?php

declare(strict_types=1);

namespace Vendor\Queue\Model;

use Magento\Framework\MessageQueue\MergerInterface;

class BatchConsumer
{
    /**
     * @param LoggerInterface $logger
     * @param BatchProcessor $batchProcessor
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly BatchProcessor $batchProcessor
    ) {
    }

    /**
     * @param array $messages
     * @return void
     */
    public function processBatch(array $messages): void
    {
        $this->logger->info('Processing batch', ['count' => count($messages)]);

        $entityIds = [];
        foreach ($messages as $message) {
            $entityIds[] = $message->getEntityId();
        }

        // Process all at once for efficiency
        $this->batchProcessor->processEntities($entityIds);
    }
}

/**
 * Message Merger for batch processing
 */
class MessageMerger implements MergerInterface
{
    /**
     * @param array $messages
     * @return array
     */
    public function merge(array $messages): array
    {
        // Group messages by operation type
        $grouped = [];
        foreach ($messages as $message) {
            $operation = $message->getOperation();
            $grouped[$operation][] = $message;
        }

        return $grouped;
    }
}
```

## Running Consumers

```bash
# Start single consumer
bin/magento queue:consumers:start vendor.custom.consumer

# Start with message limit
bin/magento queue:consumers:start vendor.custom.consumer --max-messages=100

# Start with time limit (seconds)
bin/magento queue:consumers:start vendor.custom.consumer --max-idle-time=60

# List all consumers
bin/magento queue:consumers:list

# Run via cron (etc/crontab.xml)
```

### Cron Configuration

```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Cron:etc/crontab.xsd">
    <group id="default">
        <job name="vendor_queue_consumer" instance="Magento\MessageQueue\Model\Cron\ConsumersRunner" method="run">
            <schedule>* * * * *</schedule>
        </job>
    </group>
</config>
```

## Error Handling and Retry

```xml
<!-- etc/queue_consumer.xml -->
<consumer name="vendor.retry.consumer"
          queue="vendor.queue.retry"
          connection="amqp"
          handler="Vendor\Queue\Model\RetryConsumer::process"
          maxRetries="3"
          rejectMessageOnException="true"/>
```

```php
<?php

declare(strict_types=1);

namespace Vendor\Queue\Model;

use Magento\Framework\MessageQueue\PoisonPill\PoisonPillCompareInterface;

class RetryConsumer
{
    private const MAX_RETRIES = 3;

    /**
     * @param LoggerInterface $logger
     * @param DeadLetterPublisher $deadLetterPublisher
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly DeadLetterPublisher $deadLetterPublisher
    ) {
    }

    /**
     * @param MessageInterface $message
     * @return void
     */
    public function process(MessageInterface $message): void
    {
        $retryCount = $message->getData()['retry_count'] ?? 0;

        try {
            $this->processMessage($message);
        } catch (\Exception $e) {
            if ($retryCount >= self::MAX_RETRIES) {
                // Move to dead letter queue
                $this->deadLetterPublisher->publish($message, $e->getMessage());
                $this->logger->error('Message moved to dead letter queue', [
                    'entity_id' => $message->getEntityId(),
                    'retries' => $retryCount,
                ]);
                return;
            }

            // Re-throw for automatic retry
            throw $e;
        }
    }
}
```

## Best Practices

1. **Use appropriate backend** - MySQL for dev, RabbitMQ/SQS for production
2. **Set reasonable limits** - maxMessages, maxIdleTime
3. **Implement idempotency** - Messages may be processed multiple times
4. **Handle poison pills** - Move failed messages to dead letter queue
5. **Monitor queue depth** - Alert on growing backlogs
6. **Use batch processing** - For high-volume scenarios
7. **Log comprehensively** - Track message lifecycle
8. **Test failure scenarios** - Ensure graceful degradation
