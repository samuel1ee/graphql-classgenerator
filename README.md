# GraphQL Type Generator

A PHP CLI tool that introspects a GraphQL API and generates PHP type classes from
the schema. Designed for Shopify's Admin and Storefront APIs but works against any
standard GraphQL endpoint.

Given a GraphQL schema it produces:
- **OBJECT** types → PHP classes with public typed properties
- **INPUT_OBJECT** types → PHP classes with a promoted-property constructor
- **INTERFACE** types → PHP interfaces with `@property` docblocks
- **ENUM** types → PHP backed `string` enums

## Requirements

- PHP 8.4+ with `ext-mbstring`
- Composer

## Installation

```bash
composer install
```

## Configuration

Create a YAML file (default: `./configuration.yaml`) with a `sources` map and an
optional `scalars` map. Each source becomes a subdirectory in the output and is
processed independently.

```yaml
scalars:                   # optional — global, applied to every source
  DateTime: string
  URL: string
  Money: 'float|string'
  JSON: mixed
  # ... any other custom scalar your API exposes

sources:
  AdminApi:
    namespace: Shopify\Types\AdminApi
    url: https://shopify.dev/admin-graphql-direct-proxy/2026-04

  StorefrontApi:
    namespace: Shopify\Types\StorefrontApi
    url: https://shopify.dev/storefront-graphql-direct-proxy/2026-04
    scalars:               # optional — per-source override/extension
      CustomScalar: int
```

### `scalars` map

GraphQL introspection cannot reveal the underlying representation of custom scalars,
so you must provide the mapping yourself. The five GraphQL built-in scalars are
always available automatically:

| Built-in scalar | PHP type |
|-----------------|----------|
| `Int`           | `int`    |
| `Float`         | `float`  |
| `String`        | `string` |
| `ID`            | `string` |
| `Boolean`       | `bool`   |

Any scalar not listed in the config **falls back to its GraphQL name** as a PHP
type — this will produce invalid PHP if that name is not also a generated class.
Make sure all custom scalars in your schema are mapped.

**Merge order (later wins):** built-ins → global `scalars:` → per-source `scalars:`.

### `sources` keys

| Key         | Required | Description                                                       |
|-------------|----------|-------------------------------------------------------------------|
| `namespace` | yes      | PHP namespace applied to every generated file for this source     |
| `url`       | yes      | GraphQL endpoint that accepts POST introspection queries          |
| `headers`   | no       | Map of request headers (e.g. auth tokens)                        |
| `scalars`   | no       | Per-source scalar map; extends/overrides the global `scalars` map |

**Example with auth headers:**

```yaml
sources:
  MyApi:
    namespace: My\Generated
    url: https://api.example.com/graphql
    headers:
      Authorization: Bearer <token>
      X-Api-Version: "2026-04"
    scalars:
      UUID: string
```

To generate the configuration file interactively, run:

```bash
php bin/console generate:config
```

## Usage

```bash
php bin/console generate:types [options] [output]
```

| Option / Arg    | Default                  | Description                           |
|-----------------|--------------------------|---------------------------------------|
| `-c`, `--config`| `./configuration.yaml`   | Path to the YAML configuration file  |
| `output`        | `./generated`            | Directory where files are written     |
| `-v`            | *(off)*                  | Print the name of each generated type |

**Examples:**

```bash
# Use defaults
php bin/console generate:types

# Custom config and output
php bin/console generate:types --config api.yaml ./out

# Verbose — lists every generated type
php bin/console generate:types -v
```

> **Note:** The output directory is **wiped on every run** before new files are
> written. Do not store hand-edited files there.

## Output layout

```
generated/
  AdminApi/
    Product.php
    ProductSortKeys.php   ← backed enum
    SEOInput.php          ← input object
    HasMetafields.php     ← interface
    ...
  StorefrontApi/
    ...
```

Sample generated enum (`ProductSortKeys.php`):

```php
namespace Shopify\Types\AdminApi;

/** The set of valid sort keys for the Product query. */
enum ProductSortKeys: string
{
    /** Sort by the `created_at` value. */
    case CREATED_AT = 'CREATED_AT';
    case ID = 'ID';
    // ...
}
```

Sample generated input object (`SEOInput.php`):

```php
namespace Shopify\Types\AdminApi;

/** The input fields for SEO information. */
class SEOInput
{
    /** @param string|null $title @param string|null $description */
    public function __construct(
        public string|null $title,
        public string|null $description,
    ) {}
}
```

## GraphQL → PHP type mapping

The five built-in scalars are resolved automatically. All other types come from
your `scalars` config, or fall back to the GraphQL type name.

| Source          | GraphQL type      | PHP type        |
|-----------------|-------------------|-----------------|
| Built-in        | `Int`             | `int`           |
| Built-in        | `Float`           | `float`         |
| Built-in        | `String`, `ID`    | `string`        |
| Built-in        | `Boolean`         | `bool`          |
| Config (example)| `DateTime`, `URL` | `string`        |
| Config (example)| `Money`, `Decimal`| `float\|string` |
| Config (example)| `JSON`            | `mixed`         |
| Generated class | OBJECT / ENUM     | class name      |
| Wrapper         | `NON_NULL(T)`     | `T` (non-null)  |
| Wrapper         | `LIST(T)`         | `array\|null`   |

All fields are nullable by default unless wrapped in `NON_NULL` in the schema.
PHP reserved words used as type names are suffixed with `Type` automatically.

## Code style

```bash
vendor/bin/phpcs
```
