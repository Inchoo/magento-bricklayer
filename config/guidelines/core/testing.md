# Magento 2 Testing Guidelines

## Overview

Magento 2 supports multiple testing levels: unit tests, integration tests, API functional tests, and static tests. A comprehensive test suite is essential for maintainable code.

## Test Types

| Type | Purpose | Speed | Database |
|------|---------|-------|----------|
| Unit | Test isolated classes | Fast | No |
| Integration | Test component interaction | Slow | Yes |
| API Functional | Test REST/GraphQL endpoints | Slow | Yes |
| Static | Code style/quality | Fast | No |
| MFTF | End-to-end UI tests | Slowest | Yes |

## Unit Tests

### Location

```
app/code/Vendor/Module/
├── Test/
│   └── Unit/
│       ├── Model/
│       │   └── ServiceTest.php
│       └── Helper/
│           └── DataTest.php
```

### Basic Structure

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Vendor\Module\Model\Service;
use Vendor\Module\Api\RepositoryInterface;

class ServiceTest extends TestCase
{
    /**
     * @var Service
     */
    private Service $subject;

    /**
     * @var MockObject
     */
    private MockObject $repositoryMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->repositoryMock = $this->createMock(RepositoryInterface::class);
        $this->subject = new Service($this->repositoryMock);
    }

    /**
     * @return void
     */
    public function testProcessReturnsExpectedResult(): void
    {
        // Arrange
        $input = 'test';
        $expected = 'processed_test';

        $this->repositoryMock
            ->expects($this->once())
            ->method('get')
            ->with($input)
            ->willReturn($expected);

        // Act
        $result = $this->subject->process($input);

        // Assert
        $this->assertEquals($expected, $result);
    }

    /**
     * @return void
     */
    public function testProcessThrowsExceptionOnEmptyInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Input cannot be empty');

        $this->subject->process('');
    }
}
```

### Data Providers

```php
/**
 * @dataProvider validInputDataProvider
 */
public function testValidateAcceptsValidInput(string $input, bool $expected): void
{
    $result = $this->subject->validate($input);
    $this->assertEquals($expected, $result);
}

/**
 * @return array
 */
public static function validInputDataProvider(): array
{
    return [
        'simple string' => ['hello', true],
        'with numbers' => ['hello123', true],
        'empty string' => ['', false],
    ];
}
```

### Running Unit Tests

```bash
# All unit tests
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist

# Specific module
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Vendor/Module/Test/Unit

# Specific test
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist --filter testMethodName
```

## Integration Tests

### Location

```
app/code/Vendor/Module/
├── Test/
│   └── Integration/
│       ├── Model/
│       │   └── RepositoryTest.php
│       └── _files/
│           ├── fixture.php
│           └── fixture_rollback.php
```

### Structure

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Test\Integration\Model;

use Magento\TestFramework\TestCase\AbstractController;
use Magento\TestFramework\Helper\Bootstrap;
use Vendor\Module\Api\RepositoryInterface;

/**
 * @magentoAppArea frontend
 * @magentoDbIsolation enabled
 */
class RepositoryTest extends AbstractController
{
    /**
     * @var RepositoryInterface
     */
    private RepositoryInterface $repository;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Bootstrap::getObjectManager()->get(RepositoryInterface::class);
    }

    /**
     * @magentoDataFixture Vendor_Module::Test/Integration/_files/entity.php
     */
    public function testGetReturnsEntity(): void
    {
        $entity = $this->repository->get('test_identifier');

        $this->assertNotNull($entity);
        $this->assertEquals('Test Name', $entity->getName());
    }

    /**
     * @magentoConfigFixture current_store custom/config/enabled 1
     */
    public function testFeatureWithConfig(): void
    {
        // Test with configuration
    }
}
```

### Fixtures

```php
// _files/entity.php
<?php

declare(strict_types=1);

use Magento\TestFramework\Helper\Bootstrap;
use Vendor\Module\Api\Data\EntityInterfaceFactory;
use Vendor\Module\Api\RepositoryInterface;

$objectManager = Bootstrap::getObjectManager();
$entityFactory = $objectManager->get(EntityInterfaceFactory::class);
$repository = $objectManager->get(RepositoryInterface::class);

$entity = $entityFactory->create();
$entity->setIdentifier('test_identifier');
$entity->setName('Test Name');
$repository->save($entity);
```

```php
// _files/entity_rollback.php
<?php

declare(strict_types=1);

use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\Registry;
use Vendor\Module\Api\RepositoryInterface;

$objectManager = Bootstrap::getObjectManager();
$registry = $objectManager->get(Registry::class);
$repository = $objectManager->get(RepositoryInterface::class);

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

try {
    $entity = $repository->get('test_identifier');
    $repository->delete($entity);
} catch (\Exception $e) {
    // Entity doesn't exist
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
```

### Running Integration Tests

```bash
# Configure
cd dev/tests/integration
cp phpunit.xml.dist phpunit.xml
# Edit install-config-mysql.php with database credentials

# Run tests
../../../vendor/bin/phpunit -c phpunit.xml

# Specific module
../../../vendor/bin/phpunit -c phpunit.xml ../../../app/code/Vendor/Module/Test/Integration
```

## API Functional Tests

### REST API Tests

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Test\Api;

use Magento\TestFramework\TestCase\WebapiAbstract;
use Magento\Framework\Webapi\Rest\Request;

class CustomEndpointTest extends WebapiAbstract
{
    private const RESOURCE_PATH = '/V1/custom/items';

    /**
     * @magentoApiDataFixture Vendor_Module::Test/Integration/_files/entity.php
     */
    public function testGetList(): void
    {
        $serviceInfo = [
            'rest' => [
                'resourcePath' => self::RESOURCE_PATH,
                'httpMethod' => Request::HTTP_METHOD_GET,
            ],
        ];

        $response = $this->_webApiCall($serviceInfo);

        $this->assertArrayHasKey('items', $response);
        $this->assertNotEmpty($response['items']);
    }

    /**
     * @return void
     */
    public function testCreateItem(): void
    {
        $serviceInfo = [
            'rest' => [
                'resourcePath' => self::RESOURCE_PATH,
                'httpMethod' => Request::HTTP_METHOD_POST,
            ],
        ];

        $requestData = ['item' => ['name' => 'Test Item']];
        $response = $this->_webApiCall($serviceInfo, $requestData);

        $this->assertArrayHasKey('id', $response);
    }
}
```

### GraphQL Tests

```php
<?php

declare(strict_types=1);

namespace Vendor\Module\Test\GraphQl;

use Magento\TestFramework\TestCase\GraphQlAbstract;

class CustomQueryTest extends GraphQlAbstract
{
    /**
     * @magentoApiDataFixture Vendor_Module::Test/Integration/_files/entity.php
     */
    public function testCustomQuery(): void
    {
        $query = <<<GRAPHQL
{
    customItems(pageSize: 10) {
        items {
            id
            name
        }
        total_count
    }
}
GRAPHQL;

        $response = $this->graphQlQuery($query);

        $this->assertArrayHasKey('customItems', $response);
        $this->assertGreaterThan(0, $response['customItems']['total_count']);
    }
}
```

## Static Tests

### PHP CodeSniffer

```bash
vendor/bin/phpcs --standard=Magento2 app/code/Vendor/Module
```

### PHP Mess Detector

```bash
vendor/bin/phpmd app/code/Vendor/Module text cleancode,codesize,design
```

### PHPStan

```bash
vendor/bin/phpstan analyse app/code/Vendor/Module --level=5
```

## Best Practices

### DO

1. Write tests before or alongside code (TDD)
2. Test edge cases and error conditions
3. Use meaningful test method names
4. Keep tests focused and independent
5. Use data providers for multiple scenarios
6. Clean up test data in rollback fixtures

### DON'T

1. Test framework code
2. Write tests that depend on each other
3. Use production database for tests
4. Skip writing tests for "simple" code
5. Mock everything - use real objects when practical

## Coverage

Generate coverage reports:

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist \
    --coverage-html coverage/ \
    app/code/Vendor/Module/Test/Unit
```

Aim for:
- Critical business logic: 90%+
- Models/Services: 80%+
- Overall module: 70%+
