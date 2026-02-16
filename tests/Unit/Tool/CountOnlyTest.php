<?php
declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use Inchoo\MagentoBricklayer\Mcp\Tool\CatalogTools;
use Inchoo\MagentoBricklayer\Mcp\Tool\OrderTools;
use Inchoo\MagentoBricklayer\Mcp\Tool\CustomerTools;
use Inchoo\MagentoBricklayer\Mcp\Tool\ModuleTools;
use PHPUnit\Framework\TestCase;

class CountOnlyTest extends TestCase
{
    public function testCountOnlyParameterOnListProducts(): void
    {
        $method = new \ReflectionMethod(CatalogTools::class, 'listProducts');
        $params = array_map(fn($p) => $p->getName(), $method->getParameters());
        $this->assertContains('count_only', $params);

        $countOnlyParam = $method->getParameters()[array_search('count_only', $params)];
        $this->assertFalse($countOnlyParam->getDefaultValue());
    }

    public function testCountOnlyParameterOnListOrders(): void
    {
        $method = new \ReflectionMethod(OrderTools::class, 'listOrders');
        $params = array_map(fn($p) => $p->getName(), $method->getParameters());
        $this->assertContains('count_only', $params);
    }

    public function testCountOnlyParameterOnListCustomers(): void
    {
        $method = new \ReflectionMethod(CustomerTools::class, 'listCustomers');
        $params = array_map(fn($p) => $p->getName(), $method->getParameters());
        $this->assertContains('count_only', $params);
    }

    public function testCountOnlyParameterOnListModules(): void
    {
        $method = new \ReflectionMethod(ModuleTools::class, 'listModules');
        $params = array_map(fn($p) => $p->getName(), $method->getParameters());
        $this->assertContains('count_only', $params);
    }

    public function testFieldsParameterComesAfterCountOnly(): void
    {
        $method = new \ReflectionMethod(CatalogTools::class, 'listProducts');
        $params = array_map(fn($p) => $p->getName(), $method->getParameters());
        $countOnlyIdx = array_search('count_only', $params);
        $fieldsIdx = array_search('fields', $params);
        $this->assertGreaterThan($countOnlyIdx, $fieldsIdx);
    }
}
