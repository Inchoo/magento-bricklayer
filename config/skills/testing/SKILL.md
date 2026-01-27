# Testing Skill

## Overview

Comprehensive testing ensures code quality and prevents regressions. Magento supports unit tests, integration tests, API tests, and functional tests.

## Test Types Summary

| Type | Framework | Speed | Database | Use Case |
|------|-----------|-------|----------|----------|
| Unit | PHPUnit | Fast | No | Isolated class logic |
| Integration | PHPUnit + Magento | Slow | Yes | Component interaction |
| API | PHPUnit + WebAPI | Slow | Yes | REST/GraphQL endpoints |
| MFTF | Selenium | Slowest | Yes | UI/E2E testing |

## Unit Tests

### Directory Structure

```
app/code/Vendor/Module/Test/Unit/
├── Model/
│   ├── ServiceTest.php
│   └── ValidatorTest.php
└── Helper/
    └── DataTest.php
```

### Basic Test

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Vendor\Module\Model\Calculator;
use Vendor\Module\Api\ConfigInterface;

class CalculatorTest extends TestCase
{
    private Calculator $calculator;
    private MockObject $configMock;

    protected function setUp(): void
    {
        $this->configMock = $this->createMock(ConfigInterface::class);
        $this->calculator = new Calculator($this->configMock);
    }

    public function testCalculateReturnsCorrectValue(): void
    {
        // Arrange
        $this->configMock->method('getMultiplier')->willReturn(2.0);

        // Act
        $result = $this->calculator->calculate(10);

        // Assert
        $this->assertEquals(20.0, $result);
    }

    public function testCalculateWithZeroReturnsZero(): void
    {
        $this->configMock->method('getMultiplier')->willReturn(5.0);

        $result = $this->calculator->calculate(0);

        $this->assertEquals(0.0, $result);
    }

    public function testCalculateThrowsExceptionOnNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must be non-negative');

        $this->calculator->calculate(-1);
    }
}
```

### Data Providers

```php
/**
 * @dataProvider calculationDataProvider
 */
public function testCalculateWithVariousInputs(float $input, float $multiplier, float $expected): void
{
    $this->configMock->method('getMultiplier')->willReturn($multiplier);

    $result = $this->calculator->calculate($input);

    $this->assertEquals($expected, $result);
}

public static function calculationDataProvider(): array
{
    return [
        'basic multiplication' => [10, 2.0, 20.0],
        'zero input' => [0, 5.0, 0.0],
        'decimal values' => [5.5, 2.0, 11.0],
        'one multiplier' => [100, 1.0, 100.0],
    ];
}
```

### Running Unit Tests

```bash
# All unit tests
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist

# Specific module
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Vendor/Module/Test/Unit

# Single test file
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Vendor/Module/Test/Unit/Model/CalculatorTest.php

# Single test method
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist --filter testCalculateReturnsCorrectValue
```

## Integration Tests

### Configuration

```bash
cd dev/tests/integration
cp phpunit.xml.dist phpunit.xml
# Edit install-config-mysql.php with DB credentials
```

### Test Example

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Test\Integration\Model;

use Magento\TestFramework\TestCase\AbstractController;
use Magento\TestFramework\Helper\Bootstrap;
use Vendor\Module\Api\ItemRepositoryInterface;
use Vendor\Module\Api\Data\ItemInterface;

/**
 * @magentoAppArea frontend
 * @magentoDbIsolation enabled
 */
class ItemRepositoryTest extends AbstractController
{
    private ItemRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Bootstrap::getObjectManager()->get(ItemRepositoryInterface::class);
    }

    /**
     * @magentoDataFixture Vendor_Module::Test/Integration/_files/item.php
     */
    public function testGetByIdReturnsItem(): void
    {
        $item = $this->repository->getById(1);

        $this->assertInstanceOf(ItemInterface::class, $item);
        $this->assertEquals('Test Item', $item->getName());
    }

    /**
     * @magentoConfigFixture current_store vendor_module/general/enabled 1
     */
    public function testFeatureWithConfig(): void
    {
        // Test with specific configuration
    }

    public function testGetByIdThrowsExceptionForInvalidId(): void
    {
        $this->expectException(\Magento\Framework\Exception\NoSuchEntityException::class);

        $this->repository->getById(99999);
    }
}
```

### Fixtures

```php
// Test/Integration/_files/item.php
<?php
declare(strict_types=1);

use Magento\TestFramework\Helper\Bootstrap;
use Vendor\Module\Api\Data\ItemInterfaceFactory;
use Vendor\Module\Api\ItemRepositoryInterface;

$objectManager = Bootstrap::getObjectManager();
$factory = $objectManager->get(ItemInterfaceFactory::class);
$repository = $objectManager->get(ItemRepositoryInterface::class);

$item = $factory->create();
$item->setId(1);
$item->setName('Test Item');
$item->setStatus(1);

$repository->save($item);
```

```php
// Test/Integration/_files/item_rollback.php
<?php
declare(strict_types=1);

use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\Registry;
use Vendor\Module\Api\ItemRepositoryInterface;

$objectManager = Bootstrap::getObjectManager();
$registry = $objectManager->get(Registry::class);
$repository = $objectManager->get(ItemRepositoryInterface::class);

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

try {
    $repository->deleteById(1);
} catch (\Exception $e) {
    // Item doesn't exist
}

$registry->unregister('isSecureArea');
```

## API Tests

### REST API Test

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Test\Api;

use Magento\TestFramework\TestCase\WebapiAbstract;
use Magento\Framework\Webapi\Rest\Request;

class ItemApiTest extends WebapiAbstract
{
    private const RESOURCE_PATH = '/V1/vendor-module/items';

    /**
     * @magentoApiDataFixture Vendor_Module::Test/Integration/_files/item.php
     */
    public function testGetItem(): void
    {
        $serviceInfo = [
            'rest' => [
                'resourcePath' => self::RESOURCE_PATH . '/1',
                'httpMethod' => Request::HTTP_METHOD_GET,
            ],
        ];

        $item = $this->_webApiCall($serviceInfo);

        $this->assertArrayHasKey('entity_id', $item);
        $this->assertEquals('Test Item', $item['name']);
    }

    public function testCreateItem(): void
    {
        $serviceInfo = [
            'rest' => [
                'resourcePath' => self::RESOURCE_PATH,
                'httpMethod' => Request::HTTP_METHOD_POST,
            ],
        ];

        $requestData = [
            'item' => [
                'name' => 'New Item',
                'status' => 1,
            ],
        ];

        $result = $this->_webApiCall($serviceInfo, $requestData);

        $this->assertArrayHasKey('entity_id', $result);
        $this->assertEquals('New Item', $result['name']);
    }
}
```

### GraphQL Test

```php
<?php
declare(strict_types=1);

namespace Vendor\Module\Test\GraphQl;

use Magento\TestFramework\TestCase\GraphQlAbstract;

class CustomQueryTest extends GraphQlAbstract
{
    /**
     * @magentoApiDataFixture Vendor_Module::Test/Integration/_files/item.php
     */
    public function testCustomItemQuery(): void
    {
        $query = <<<GRAPHQL
{
    customItem(id: 1) {
        id
        name
        status
    }
}
GRAPHQL;

        $response = $this->graphQlQuery($query);

        $this->assertArrayHasKey('customItem', $response);
        $this->assertEquals(1, $response['customItem']['id']);
        $this->assertEquals('Test Item', $response['customItem']['name']);
    }
}
```

## Mocking Techniques

### Mock Builder

```php
$mock = $this->getMockBuilder(SomeClass::class)
    ->disableOriginalConstructor()
    ->onlyMethods(['methodToMock'])
    ->getMock();

$mock->expects($this->once())
    ->method('methodToMock')
    ->with('arg1', 'arg2')
    ->willReturn('result');
```

### Consecutive Calls

```php
$mock->method('getValue')
    ->willReturnOnConsecutiveCalls('first', 'second', 'third');
```

### Callback

```php
$mock->method('process')
    ->willReturnCallback(function ($arg) {
        return strtoupper($arg);
    });
```

## Code Coverage

```bash
# Generate HTML coverage report
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist \
    --coverage-html coverage/ \
    app/code/Vendor/Module/Test/Unit

# Coverage with filter
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist \
    --coverage-html coverage/ \
    --coverage-filter app/code/Vendor/Module/Model \
    app/code/Vendor/Module/Test/Unit
```

## Best Practices

1. **Name tests descriptively** - `testMethodNameReturnsBehavior`
2. **One assertion per test** - When practical
3. **Use data providers** - For multiple scenarios
4. **Test edge cases** - Empty, null, boundary values
5. **Mock external dependencies** - Database, APIs, files
6. **Keep tests fast** - Unit tests should be milliseconds
7. **Clean up after tests** - Use rollback fixtures
8. **Test error conditions** - Exceptions, invalid input
9. **Maintain test coverage** - Aim for 70%+ on business logic
