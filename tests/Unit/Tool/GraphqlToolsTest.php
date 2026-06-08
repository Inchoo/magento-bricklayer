<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use Inchoo\MagentoBricklayer\Mcp\Tool\GraphqlTools;
use PHPUnit\Framework\TestCase;

/**
 * Tests for GraphQL type-kind mislabel (B2) and null detail description (B5) bugs.
 */
class GraphqlToolsTest extends TestCase
{
    private \ReflectionMethod $getTypeKind;
    private GraphqlTools $tool;

    protected function setUp(): void
    {
        $this->tool = (new \ReflectionClass(GraphqlTools::class))->newInstanceWithoutConstructor();
        $this->getTypeKind = new \ReflectionMethod(GraphqlTools::class, 'getTypeKind');
    }

    public function testItReportsInputObjectTypeClassesAsKindInputObject(): void
    {
        $inputType = (new \ReflectionClass(InputObjectType::class))->newInstanceWithoutConstructor();
        $result = $this->getTypeKind->invoke($this->tool, $inputType);

        $this->assertSame('INPUT_OBJECT', $result);
    }

    public function testItReportsObjectTypeClassesAsKindObject(): void
    {
        $objectType = (new \ReflectionClass(ObjectType::class))->newInstanceWithoutConstructor();
        $result = $this->getTypeKind->invoke($this->tool, $objectType);

        $this->assertSame('OBJECT', $result);
    }

    public function testItDoesNotLetTheObjectArmShadowInputObjectTypes(): void
    {
        $inputType = (new \ReflectionClass(InputObjectType::class))->newInstanceWithoutConstructor();
        $result = $this->getTypeKind->invoke($this->tool, $inputType);

        $this->assertNotSame('OBJECT', $result, 'InputObjectType must not be reported as OBJECT');
    }

    public function testItReturnsANonNullDescriptionInTheTypeDetailViewWhenTheTypeHasOne(): void
    {
        $ref = new \ReflectionMethod(GraphqlTools::class, 'resolveTypeDescription');
        $inputType = new InputObjectType([
            'name' => 'TestInput',
            'fields' => [],
            'description' => 'A test input type',
        ]);

        $description = $ref->invoke($this->tool, $inputType);

        $this->assertNotNull($description);
        $this->assertSame('A test input type', $description);
    }

    public function testItReturnsTheSameDescriptionInDetailViewAsInTheListView(): void
    {
        $ref = new \ReflectionMethod(GraphqlTools::class, 'resolveTypeDescription');
        $objectType = new ObjectType([
            'name' => 'TestObject',
            'fields' => [],
            'description' => 'A test object type',
        ]);

        $description = $ref->invoke($this->tool, $objectType);

        $this->assertSame($objectType->description(), $description);
    }
}
