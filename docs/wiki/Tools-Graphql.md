# GraphQL Tools

1 unified tool for inspecting the Magento GraphQL schema.

## `graphql-inspect`

Inspect the GraphQL schema by target: types, queries, mutations, or resolvers.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `target` | string | *required* | `types`, `queries`, `mutations`, `resolvers` |
| `name` | string | `""` | Type name for detail view |
| `kind` | string | `""` | Filter by kind |

### Targets

**`types`** — List all GraphQL types registered in the schema.

```
graphql-inspect target="types"
```

**`types` with `name`** — Get detailed fields and structure for a specific type.

```
graphql-inspect target="types" name="ProductInterface"
```

**`queries`** — List all available GraphQL queries.

```
graphql-inspect target="queries"
```

**`mutations`** — List all available GraphQL mutations.

```
graphql-inspect target="mutations"
```

**`resolvers`** — Returns guidance on resolver architecture. Note: Magento does not expose resolver class mappings programmatically, so this target provides general resolver development guidance rather than runtime data.

### Usage Examples

Inspect what data a product query returns:

```
graphql-inspect target="types" name="SimpleProduct"
```

Find available cart mutations:

```
graphql-inspect target="mutations"
→ then search results for "cart"
```

Check what input types a mutation expects:

```
graphql-inspect target="types" name="AddSimpleProductsToCartInput"
```
