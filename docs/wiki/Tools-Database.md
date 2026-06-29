# Database Tools

2 tools for inspecting database schema and executing read-only queries.

## `database-schema`

Returns table structure with columns, indexes, and foreign keys.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `table` | string | `""` | Exact table name |
| `pattern` | string | `""` | Table name pattern to search |

**Security:** Table names are validated against actual database tables before use in queries to prevent SQL injection.

## `database-query`

Execute a read-only SELECT query against the Magento database.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `query` | string | *required* | SQL SELECT query |
| `limit` | int | 100 | Maximum rows returned |

**Restrictions:**
- Only `SELECT` statements are allowed
- Row limit is enforced via `tools.database-query.max_rows` config
- Sensitive configuration paths are masked in results (`payment/*`, `carriers/*`, `oauth/*`, etc.)

## Configuration

```json
{
    "tools": {
        "database-query": {
            "enabled": true,
            "max_rows": 100
        }
    }
}
```
