# Declarative Schema in Magento 2

## Overview

Declarative schema (db_schema.xml) is the modern approach to managing database structure in Magento 2.3+. It replaces the old InstallSchema/UpgradeSchema scripts.

## Location

```
app/code/Vendor/Module/
├── etc/
│   ├── db_schema.xml
│   └── db_schema_whitelist.json
```

## Basic Structure

```xml
<?xml version="1.0"?>
<schema xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Setup/Declaration/Schema/etc/schema.xsd">

    <table name="vendor_module_entity" resource="default" engine="innodb" comment="Custom Entity Table">
        <!-- Primary Key -->
        <column xsi:type="int" name="entity_id" unsigned="true" nullable="false" identity="true"
                comment="Entity ID"/>

        <!-- Foreign Key Reference -->
        <column xsi:type="int" name="store_id" unsigned="true" nullable="false" default="0"
                comment="Store ID"/>

        <!-- String Columns -->
        <column xsi:type="varchar" name="identifier" nullable="false" length="255"
                comment="Identifier"/>
        <column xsi:type="varchar" name="title" nullable="true" length="255"
                comment="Title"/>
        <column xsi:type="text" name="content" nullable="true"
                comment="Content"/>

        <!-- Numeric Columns -->
        <column xsi:type="decimal" name="price" precision="20" scale="6" nullable="false" default="0"
                comment="Price"/>
        <column xsi:type="smallint" name="status" unsigned="true" nullable="false" default="1"
                comment="Status"/>

        <!-- Date/Time Columns -->
        <column xsi:type="timestamp" name="created_at" on_update="false" nullable="false"
                default="CURRENT_TIMESTAMP" comment="Created At"/>
        <column xsi:type="timestamp" name="updated_at" on_update="true" nullable="false"
                default="CURRENT_TIMESTAMP" comment="Updated At"/>

        <!-- Boolean (stored as smallint) -->
        <column xsi:type="smallint" name="is_active" unsigned="true" nullable="false" default="1"
                comment="Is Active"/>

        <!-- Primary Key Constraint -->
        <constraint xsi:type="primary" referenceId="PRIMARY">
            <column name="entity_id"/>
        </constraint>

        <!-- Foreign Key Constraint -->
        <constraint xsi:type="foreign" referenceId="VENDOR_MODULE_ENTITY_STORE_ID_STORE_STORE_ID"
                    table="vendor_module_entity" column="store_id"
                    referenceTable="store" referenceColumn="store_id"
                    onDelete="CASCADE"/>

        <!-- Unique Constraint -->
        <constraint xsi:type="unique" referenceId="VENDOR_MODULE_ENTITY_IDENTIFIER_STORE_ID">
            <column name="identifier"/>
            <column name="store_id"/>
        </constraint>

        <!-- Indexes -->
        <index referenceId="VENDOR_MODULE_ENTITY_STATUS" indexType="btree">
            <column name="status"/>
        </index>
        <index referenceId="VENDOR_MODULE_ENTITY_CREATED_AT" indexType="btree">
            <column name="created_at"/>
        </index>

        <!-- Full-text Index -->
        <index referenceId="VENDOR_MODULE_ENTITY_TITLE_CONTENT" indexType="fulltext">
            <column name="title"/>
            <column name="content"/>
        </index>
    </table>

</schema>
```

## Column Types

| Type | XML Attribute | Description |
|------|---------------|-------------|
| int | `xsi:type="int"` | Integer (4 bytes) |
| smallint | `xsi:type="smallint"` | Small integer (2 bytes) |
| bigint | `xsi:type="bigint"` | Big integer (8 bytes) |
| tinyint | `xsi:type="tinyint"` | Tiny integer (1 byte) |
| decimal | `xsi:type="decimal"` | Decimal with precision/scale |
| float | `xsi:type="float"` | Floating point |
| varchar | `xsi:type="varchar"` | Variable string with length |
| text | `xsi:type="text"` | Text blob |
| mediumtext | `xsi:type="mediumtext"` | Medium text blob |
| longtext | `xsi:type="longtext"` | Long text blob |
| blob | `xsi:type="blob"` | Binary blob |
| mediumblob | `xsi:type="mediumblob"` | Medium binary blob |
| timestamp | `xsi:type="timestamp"` | Timestamp |
| datetime | `xsi:type="datetime"` | Date and time |
| date | `xsi:type="date"` | Date only |
| boolean | `xsi:type="boolean"` | Boolean |

## Common Column Attributes

```xml
<!-- Auto-increment primary key -->
<column xsi:type="int" name="id" unsigned="true" nullable="false" identity="true"/>

<!-- Nullable with default -->
<column xsi:type="varchar" name="name" nullable="true" length="255" default=""/>

<!-- Decimal with precision -->
<column xsi:type="decimal" name="amount" precision="20" scale="6" unsigned="false" nullable="false" default="0.000000"/>

<!-- Timestamp with auto-update -->
<column xsi:type="timestamp" name="updated_at" on_update="true" nullable="false" default="CURRENT_TIMESTAMP"/>
```

## Constraints

### Primary Key

```xml
<constraint xsi:type="primary" referenceId="PRIMARY">
    <column name="entity_id"/>
</constraint>
```

### Foreign Key

```xml
<constraint xsi:type="foreign" referenceId="FK_REFERENCE_ID"
            table="current_table" column="foreign_column"
            referenceTable="parent_table" referenceColumn="parent_column"
            onDelete="CASCADE"/>
```

onDelete options: `CASCADE`, `SET NULL`, `NO ACTION`, `RESTRICT`

### Unique

```xml
<constraint xsi:type="unique" referenceId="UNIQUE_IDENTIFIER_STORE">
    <column name="identifier"/>
    <column name="store_id"/>
</constraint>
```

## Indexes

```xml
<!-- B-tree index (default) -->
<index referenceId="INDEX_NAME" indexType="btree">
    <column name="column_name"/>
</index>

<!-- Full-text index -->
<index referenceId="FTI_VENDOR_MODULE_TITLE" indexType="fulltext">
    <column name="title"/>
    <column name="description"/>
</index>
```

## Whitelist Generation

After modifying db_schema.xml, generate the whitelist:

```bash
bin/magento setup:db-declaration:generate-whitelist --module-name=Vendor_Module
```

This creates/updates `etc/db_schema_whitelist.json`:

```json
{
    "vendor_module_entity": {
        "column": {
            "entity_id": true,
            "identifier": true,
            "title": true
        },
        "index": {
            "VENDOR_MODULE_ENTITY_STATUS": true
        },
        "constraint": {
            "PRIMARY": true,
            "VENDOR_MODULE_ENTITY_IDENTIFIER_STORE_ID": true
        }
    }
}
```

## Applying Changes

```bash
bin/magento setup:upgrade
```

Or with verbose output:
```bash
bin/magento setup:upgrade --verbose
```

## Dropping Columns/Tables

To drop a column, remove it from db_schema.xml but keep it in whitelist, then run:

```bash
bin/magento setup:upgrade
```

For destructive operations, you may need:
```bash
bin/magento setup:upgrade --dry-run
```

## Renaming Columns

Use the `rename` feature (Magento 2.4.3+):

```xml
<column xsi:type="varchar" name="new_column_name" nullable="false" length="255"
        comment="New Name" onCreate="migrateDataFrom(old_column_name)"/>
```

## Best Practices

1. **Always generate whitelist** after schema changes
2. **Use meaningful referenceId** for constraints and indexes
3. **Document columns** with comment attribute
4. **Use appropriate column types** - don't use varchar(255) for everything
5. **Create indexes** for frequently queried columns
6. **Foreign keys with CASCADE** for dependent data
7. **Test migrations** thoroughly before production
