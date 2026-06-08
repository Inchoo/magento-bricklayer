<?php

/**
 * Copyright (c) Inchoo. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Inchoo\MagentoBricklayer\Tests\Unit\Tool;

use Inchoo\MagentoBricklayer\Mcp\Tool\CatalogTools;
use Inchoo\MagentoBricklayer\Mcp\Tool\CustomerTools;
use PHPUnit\Framework\TestCase;

/**
 * Tests for B21 (deleteCustomerAddress secure area) and B22 (addProductMedia MIME parsing).
 *
 * B21: deleteCustomerAddress must wrap its delete call in withSecureArea(), matching
 *      its sibling deletions (deleteCustomer, deleteProduct, deleteCategory).
 *
 * B22: addProductMedia must parse the MIME type and file extension from the data-URI
 *      prefix instead of hardcoding image/jpeg/.jpg, and must explicitly reject
 *      a file argument that is neither a data-URI nor an implemented file path.
 */
class MiscToolsFixesTest extends TestCase
{
    // ── B21: deleteCustomerAddress ─────────────────────────────────────────

    /**
     * Verify that the deleteCustomerAddress method body delegates to withSecureArea(),
     * matching the pattern of its sibling delete methods (deleteCustomer, deleteProduct,
     * deleteCategory).  The actual Magento registry flag is integration-only; this test
     * verifies the structural fix (the wrap is present) via source-code inspection,
     * which is red before the fix and green after — the canonical TDD contract.
     */
    public function testItDeletesACustomerAddressWithinTheSecureArea(): void
    {
        $rm = new \ReflectionMethod(CustomerTools::class, 'deleteCustomerAddress');
        $lines = file((string) $rm->getFileName()) ?: [];
        $body = implode('', array_slice(
            $lines,
            $rm->getStartLine() - 1,
            $rm->getEndLine() - $rm->getStartLine() + 1
        ));

        $this->assertStringContainsString(
            'withSecureArea',
            $body,
            'deleteCustomerAddress must delegate to withSecureArea() to match sibling delete tools'
        );
    }

    // ── B22: addProductMedia MIME parsing ──────────────────────────────────

    /**
     * A data:image/png URI must produce mime=image/png and ext=.png, not the
     * previously hardcoded image/jpeg / .jpg.
     */
    public function testItStoresAPngDataUriWithThePngMimeTypeAndExtension(): void
    {
        $ref = new \ReflectionClass(CatalogTools::class);
        $method = $ref->getMethod('parseMimeFromDataUri');

        $instance = $ref->newInstanceWithoutConstructor();

        $result = $method->invoke($instance, 'data:image/png;base64,abc123');

        $this->assertSame('image/png', $result['mime']);
        $this->assertStringEndsWith('.png', $result['name']);
    }

    /**
     * A file argument that is neither a data-URI nor an implemented file path
     * must return an error instead of silently calling create() with no image
     * content.
     */
    public function testItRejectsAMediaInputThatIsNeitherADataUriNorAnImplementedFilePath(): void
    {
        // The validation must happen before requireMagento() so that it is
        // unit-testable without a Magento bootstrap.
        $tools = new CatalogTools();
        $result = $tools->addProductMedia('SKU-123', 'image', '/some/local/path.jpg');

        $this->assertIsArray($result);
        $this->assertTrue($result['error'], 'Expected error key to be true');
        $this->assertStringContainsStringIgnoringCase('data-uri', $result['message']);
    }
}
