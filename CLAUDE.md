# GraphQL Type Generator — codebase guide

PHP CLI tool: introspects a GraphQL endpoint → generates PHP type classes.

## Architecture / data flow

```
bin/console generate:types
│
├─ src/Command/GenerateTypes.php      ← Symfony Console command; loops over sources
│   ├─ src/SchemaFetcher.php          ← POSTs introspection query, returns schema array
│   │       keyed by type name
│   ├─ src/SchemaParser.php           ← denormalizeSchema(): yields Type DTOs for
│   │       OBJECT / INTERFACE / INPUT_OBJECT / ENUM; skips __ introspection types
│   └─ src/FileWriter.php             ← typeToClass(): renders Type to PHP string;
│           emptyDir() wipes output dir before each source run
│
src/Types/
  SchemaTypes/
    Type.php           ← main DTO (kind, name, fields, inputFields, enumValues, …)
    Field.php          ← field on an OBJECT/INTERFACE
    InputField.php     ← field on an INPUT_OBJECT (adds defaultValue)
    EnumValue.php      ← single enum case (name + description)
    TypeKind.php       ← backed enum: SCALAR OBJECT INTERFACE UNION ENUM
                           INPUT_OBJECT LIST NON_NULL
  PhpFieldType.php     ← parseGraphQLFieldType($type, $scalarMap): maps GraphQL
                           types → PHP type strings using a caller-supplied scalar
                           map; handles LIST, NON_NULL, UNION/INTERFACE possibleTypes;
                           BUILTIN_SCALARS const provides Int/Float/String/ID/Boolean;
                           escapes PHP reserved words by appending 'Type'
src/Utils/
  DescriptionProcessor.php  ← wraps descriptions into docblock lines (word-wrap)

bin/console generate:config   ← interactive YAML wizard (src/Command/GenerateConfig.php)
```

## Configuration (`configuration.yaml`)

```yaml
scalars:                                   # optional global scalar→PHP map
  DateTime: string
  Money: 'float|string'
  JSON: mixed

sources:
  AdminApi:
    namespace: Shopify\Types\AdminApi      # PHP namespace for generated files
    url: https://shopify.dev/...           # GraphQL endpoint (POST introspection)
    headers:                               # optional request headers
      Authorization: Bearer xxx
    scalars:                               # optional per-source override
      CustomScalar: int
```

Scalar resolution order (later wins): `PhpFieldType::BUILTIN_SCALARS` → global
`scalars:` → per-source `scalars:`. Unmapped scalars fall back to their GraphQL
name, producing invalid PHP — always map every custom scalar your API uses.

`extensions` key seen in the sample config is **not implemented** — `GenerateTypes`
only reads `url`, `headers`, `namespace`, `scalars`.

## Commands

```bash
# Generate types (defaults: config=./configuration.yaml, output=./generated)
php bin/console generate:types [-c path/to/config.yaml] [output-dir] [-v]

# Interactive config wizard
php bin/console generate:config

# Lint
vendor/bin/phpcs
```

## Conventions

- `declare(strict_types=1)` in every file
- Classes are `final` where possible
- Constructor property promotion throughout DTOs
- PSR-4: `Aksonov\GraphqlGenerator\` → `src/`

## Gotchas

- `generated/` is **gitignored** (via `generated/.gitignore`) and **wiped on each
  run** — never put hand-edited files there
- The introspection query in `SchemaFetcher` hardcodes `ofType` nesting to 4
  levels; deeply nested wrapper types beyond that are silently truncated
- No test suite exists — verify by running the generator and inspecting output
- PHP reserved words in type names are auto-escaped (`list` → `listType`, etc.)
  via `PhpFieldType::escape()`
