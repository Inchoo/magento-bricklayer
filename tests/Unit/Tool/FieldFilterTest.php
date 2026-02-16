<?php
declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use Inchoo\MagentoBricklayer\Mcp\Tool\CatalogTools;
use Inchoo\MagentoBricklayer\Mcp\Tool\OrderTools;
use Inchoo\MagentoBricklayer\Mcp\Tool\CustomerTools;
use PHPUnit\Framework\TestCase;

class FieldFilterTest extends TestCase
{
    public function testFilterFieldsReturnsAllWhenEmpty(): void
    {
        $data = ['sku' => 'ABC', 'name' => 'Test', 'price' => 10.00];
        $this->assertEquals($data, $this->invokeFilterFields(new CatalogTools(), $data, ''));
    }

    public function testFilterFieldsFiltersToRequestedKeys(): void
    {
        $data = ['sku' => 'ABC', 'name' => 'Test', 'price' => 10.00, 'status' => 1];
        $result = $this->invokeFilterFields(new CatalogTools(), $data, 'sku,price');
        $this->assertEquals(['sku' => 'ABC', 'price' => 10.00], $result);
    }

    public function testFilterFieldsIgnoresNonexistentFields(): void
    {
        $data = ['sku' => 'ABC', 'name' => 'Test'];
        $result = $this->invokeFilterFields(new CatalogTools(), $data, 'sku,nonexistent');
        $this->assertEquals(['sku' => 'ABC'], $result);
    }

    public function testFilterFieldsHandlesWhitespace(): void
    {
        $data = ['sku' => 'ABC', 'name' => 'Test', 'price' => 10.00];
        $result = $this->invokeFilterFields(new CatalogTools(), $data, 'sku, name, price');
        $this->assertEquals($data, $result);
    }

    public function testOrderToolsHasFilterFields(): void
    {
        $data = ['entity_id' => 1, 'increment_id' => '000000001', 'status' => 'pending'];
        $result = $this->invokeFilterFields(new OrderTools(), $data, 'increment_id,status');
        $this->assertEquals(['increment_id' => '000000001', 'status' => 'pending'], $result);
    }

    public function testCustomerToolsHasFilterFields(): void
    {
        $data = ['id' => 1, 'email' => 'test@test.com', 'firstname' => 'John'];
        $result = $this->invokeFilterFields(new CustomerTools(), $data, 'email');
        $this->assertEquals(['email' => 'test@test.com'], $result);
    }

    public function testFieldsParameterExistsOnGetProduct(): void
    {
        $ref = new \ReflectionMethod(CatalogTools::class, 'getProduct');
        $params = array_map(fn($p) => $p->getName(), $ref->getParameters());
        $this->assertContains('fields', $params);
    }

    public function testFieldsParameterExistsOnListProducts(): void
    {
        $ref = new \ReflectionMethod(CatalogTools::class, 'listProducts');
        $params = array_map(fn($p) => $p->getName(), $ref->getParameters());
        $this->assertContains('fields', $params);
    }

    public function testFieldsParameterExistsOnGetOrder(): void
    {
        $ref = new \ReflectionMethod(OrderTools::class, 'getOrder');
        $params = array_map(fn($p) => $p->getName(), $ref->getParameters());
        $this->assertContains('fields', $params);
    }

    public function testFieldsParameterExistsOnListOrders(): void
    {
        $ref = new \ReflectionMethod(OrderTools::class, 'listOrders');
        $params = array_map(fn($p) => $p->getName(), $ref->getParameters());
        $this->assertContains('fields', $params);
    }

    public function testFieldsParameterExistsOnGetCustomer(): void
    {
        $ref = new \ReflectionMethod(CustomerTools::class, 'getCustomer');
        $params = array_map(fn($p) => $p->getName(), $ref->getParameters());
        $this->assertContains('fields', $params);
    }

    public function testFieldsParameterExistsOnListCustomers(): void
    {
        $ref = new \ReflectionMethod(CustomerTools::class, 'listCustomers');
        $params = array_map(fn($p) => $p->getName(), $ref->getParameters());
        $this->assertContains('fields', $params);
    }

    private function invokeFilterFields(object $instance, array $data, string $fields): array
    {
        $ref = new \ReflectionMethod($instance, 'filterFields');
        return $ref->invoke($instance, $data, $fields);
    }
}
