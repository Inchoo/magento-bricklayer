<?php
/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Mcp\Prompt;

use Mcp\Capability\Attribute\McpPrompt;

/**
 * Provides MCP prompts for creating Magento tests.
 */
class TestPrompts
{
    /**
     * Creates a unit test class.
     *
     * @param string $vendor The vendor name
     * @param string $module The module name
     * @param string $classToTest Fully qualified class name to test
     * @return array<array<string, mixed>> The prompt messages
     */
    #[McpPrompt(
        name: 'create-unit-test',
        description: 'Creates a PHPUnit unit test for a Magento class'
    )]
    public function createUnitTest(
        string $vendor,
        string $module,
        string $classToTest
    ): array {
        $moduleName = "{$vendor}_{$module}";

        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => <<<PROMPT
Create a unit test for {$classToTest}:

**Module:** {$moduleName}
**Class to Test:** {$classToTest}

Generate:

**Test/Unit/{TestClassName}Test.php**

Requirements:
- Extend \PHPUnit\Framework\TestCase
- Use @covers annotation
- Mock all dependencies using createMock() or getMockBuilder()
- Test all public methods
- Include positive and negative test cases
- Use data providers for multiple input scenarios
- Follow AAA pattern (Arrange, Act, Assert)

Structure:
```php
<?php

declare(strict_types=1);

namespace {$vendor}\\{$module}\\Test\\Unit;

use PHPUnit\\Framework\\TestCase;
use PHPUnit\\Framework\\MockObject\\MockObject;

/**
 * @covers \\{$classToTest}
 */
class {TestClassName}Test extends TestCase
{
    /**
     * @var {ClassType}
     */
    private {ClassType} \$subject;

    /**
     * @var MockObject
     */
    private MockObject \$dependencyMock;

    protected function setUp(): void
    {
        \$this->dependencyMock = \$this->createMock(DependencyInterface::class);
        \$this->subject = new {ClassName}(
            \$this->dependencyMock
        );
    }

    public function testMethodReturnsExpectedResult(): void
    {
        // Arrange
        \$this->dependencyMock->method('someMethod')->willReturn('value');

        // Act
        \$result = \$this->subject->methodToTest();

        // Assert
        \$this->assertEquals('expected', \$result);
    }

    /**
     * @dataProvider invalidInputDataProvider
     */
    public function testMethodThrowsExceptionOnInvalidInput(\$input): void
    {
        \$this->expectException(\\InvalidArgumentException::class);
        \$this->subject->methodToTest(\$input);
    }

    public static function invalidInputDataProvider(): array
    {
        return [
            'empty string' => [''],
            'null value' => [null],
        ];
    }
}
```
PROMPT
                ],
            ],
        ];
    }

    /**
     * Creates an integration test.
     *
     * @param string $vendor The vendor name
     * @param string $module The module name
     * @param string $testSubject What is being tested (e.g., "Product Repository")
     * @return array<array<string, mixed>> The prompt messages
     */
    #[McpPrompt(
        name: 'create-integration-test',
        description: 'Creates a Magento integration test'
    )]
    public function createIntegrationTest(
        string $vendor,
        string $module,
        string $testSubject
    ): array {
        $moduleName = "{$vendor}_{$module}";

        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => <<<PROMPT
Create an integration test for {$testSubject}:

**Module:** {$moduleName}
**Test Subject:** {$testSubject}

Generate:

**Test/Integration/{TestClassName}Test.php**

Requirements:
- Extend \Magento\TestFramework\TestCase\AbstractController or AbstractBackendController
- Use @magentoAppArea annotation (frontend/adminhtml)
- Use @magentoDbIsolation enabled
- Use fixtures for test data (@magentoDataFixture)
- Test real component interaction
- Use ObjectManager for service retrieval

Structure:
```php
<?php

declare(strict_types=1);

namespace {$vendor}\\{$module}\\Test\\Integration;

use Magento\\TestFramework\\TestCase\\AbstractController;
use Magento\\TestFramework\\Helper\\Bootstrap;

/**
 * @magentoAppArea frontend
 * @magentoDbIsolation enabled
 */
class {TestClassName}Test extends AbstractController
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private \Magento\Framework\ObjectManagerInterface \$objectManager;

    /**
     * @var mixed
     */
    private mixed \$subject;

    protected function setUp(): void
    {
        parent::setUp();
        \$this->objectManager = Bootstrap::getObjectManager();
        \$this->subject = \$this->objectManager->get(SubjectClass::class);
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testFeatureWithFixture(): void
    {
        // Test with fixture data
        \$result = \$this->subject->doSomething('simple');
        \$this->assertNotNull(\$result);
    }

    /**
     * @magentoConfigFixture current_store custom/config/path 1
     */
    public function testFeatureWithConfig(): void
    {
        // Test with custom configuration
    }
}
```

Fixture file example (Test/Integration/_files/custom_fixture.php):
```php
<?php
use Magento\\TestFramework\\Helper\\Bootstrap;
\$objectManager = Bootstrap::getObjectManager();
// Create test data
```

Rollback file (Test/Integration/_files/custom_fixture_rollback.php):
```php
<?php
// Clean up test data
```
PROMPT
                ],
            ],
        ];
    }

    /**
     * Creates an API functional test.
     *
     * @param string $vendor The vendor name
     * @param string $module The module name
     * @param string $apiEndpoint API endpoint to test
     * @param string $httpMethod HTTP method (GET, POST, PUT, DELETE)
     * @return array<array<string, mixed>> The prompt messages
     */
    #[McpPrompt(
        name: 'create-api-test',
        description: 'Creates a functional test for a REST API endpoint'
    )]
    public function createApiTest(
        string $vendor,
        string $module,
        string $apiEndpoint,
        string $httpMethod = 'GET'
    ): array {
        $moduleName = "{$vendor}_{$module}";

        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => <<<PROMPT
Create an API functional test:

**Module:** {$moduleName}
**Endpoint:** {$apiEndpoint}
**HTTP Method:** {$httpMethod}

Generate:

**Test/Api/{TestClassName}Test.php**

Requirements:
- Extend \Magento\TestFramework\TestCase\WebapiAbstract
- Test REST and/or SOAP interfaces
- Use @magentoApiDataFixture for test data
- Verify response structure and status codes
- Test authentication requirements
- Test error scenarios

Structure:
```php
<?php

declare(strict_types=1);

namespace {$vendor}\\{$module}\\Test\\Api;

use Magento\\TestFramework\\TestCase\\WebapiAbstract;
use Magento\\Framework\\Webapi\\Rest\\Request;

class {TestClassName}Test extends WebapiAbstract
{
    private const RESOURCE_PATH = '{$apiEndpoint}';
    private const SERVICE_NAME = 'serviceName';
    private const SERVICE_VERSION = 'V1';

    /**
     * @magentoApiDataFixture Magento/Customer/_files/customer.php
     */
    public function testEndpointReturnsExpectedData(): void
    {
        \$serviceInfo = [
            'rest' => [
                'resourcePath' => self::RESOURCE_PATH,
                'httpMethod' => Request::HTTP_METHOD_{$httpMethod},
            ],
            'soap' => [
                'service' => self::SERVICE_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_NAME . 'OperationName',
            ],
        ];

        \$response = \$this->_webApiCall(\$serviceInfo);

        \$this->assertArrayHasKey('expected_key', \$response);
    }

    public function testEndpointRequiresAuthentication(): void
    {
        \$this->expectException(\\Exception::class);
        \$this->expectExceptionMessage('Consumer is not authorized');

        \$serviceInfo = [
            'rest' => [
                'resourcePath' => self::RESOURCE_PATH,
                'httpMethod' => Request::HTTP_METHOD_{$httpMethod},
            ],
        ];

        \$this->_webApiCall(\$serviceInfo);
    }
}
```
PROMPT
                ],
            ],
        ];
    }

    /**
     * Creates a GraphQL test.
     *
     * @param string $vendor The vendor name
     * @param string $module The module name
     * @param string $queryName GraphQL query or mutation name
     * @param string $queryType Type: query or mutation
     * @return array<array<string, mixed>> The prompt messages
     */
    #[McpPrompt(
        name: 'create-graphql-test',
        description: 'Creates a functional test for a GraphQL query or mutation'
    )]
    public function createGraphqlTest(
        string $vendor,
        string $module,
        string $queryName,
        string $queryType = 'query'
    ): array {
        $moduleName = "{$vendor}_{$module}";

        return [
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => <<<PROMPT
Create a GraphQL functional test:

**Module:** {$moduleName}
**{$queryType} Name:** {$queryName}

Generate:

**Test/GraphQl/{TestClassName}Test.php**

Requirements:
- Extend \Magento\TestFramework\TestCase\GraphQlAbstract
- Use @magentoApiDataFixture for test data
- Test query/mutation response structure
- Test with different inputs
- Test authorization requirements
- Test error handling

Structure:
```php
<?php

declare(strict_types=1);

namespace {$vendor}\\{$module}\\Test\\GraphQl;

use Magento\\TestFramework\\TestCase\\GraphQlAbstract;
use Magento\\TestFramework\\Helper\\Bootstrap;

class {TestClassName}Test extends GraphQlAbstract
{
    /**
     * @magentoApiDataFixture Magento/Customer/_files/customer.php
     */
    public function test{$queryName}ReturnsExpectedData(): void
    {
        \$query = <<<GRAPHQL
{$queryType} {
    {$queryName}(id: 1) {
        id
        name
    }
}
GRAPHQL;

        \$response = \$this->graphQlQuery(\$query);

        \$this->assertArrayHasKey('{$queryName}', \$response);
        \$this->assertNotEmpty(\$response['{$queryName}']);
    }

    public function test{$queryName}WithInvalidInputReturnsError(): void
    {
        \$query = <<<GRAPHQL
{$queryType} {
    {$queryName}(id: -1) {
        id
    }
}
GRAPHQL;

        \$this->expectException(\\Exception::class);
        \$this->graphQlQuery(\$query);
    }

    /**
     * For mutations requiring customer authentication
     */
    public function test{$queryName}RequiresCustomerAuth(): void
    {
        \$mutation = <<<GRAPHQL
mutation {
    {$queryName}(input: { field: "value" }) {
        success
    }
}
GRAPHQL;

        // Get customer token
        \$customerToken = \$this->getCustomerToken('customer@example.com', 'password');
        \$headerMap = ['Authorization' => 'Bearer ' . \$customerToken];

        \$response = \$this->graphQlMutation(\$mutation, [], '', \$headerMap);

        \$this->assertTrue(\$response['{$queryName}']['success']);
    }

    /**
     * @param string \$email
     * @param string \$password
     * @return string
     */
    private function getCustomerToken(string \$email, string \$password): string
    {
        \$mutation = <<<GRAPHQL
mutation {
    generateCustomerToken(email: "{\$email}", password: "{\$password}") {
        token
    }
}
GRAPHQL;

        \$response = \$this->graphQlMutation(\$mutation);
        return \$response['generateCustomerToken']['token'];
    }
}
```
PROMPT
                ],
            ],
        ];
    }
}
