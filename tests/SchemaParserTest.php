<?php

declare(strict_types=1);

namespace Aksonov\GraphqlGenerator\Tests;

use Aksonov\GraphqlGenerator\SchemaParser;
use Aksonov\GraphqlGenerator\Types\SchemaTypes\TypeKind;
use PHPUnit\Framework\TestCase;

final class SchemaParserTest extends TestCase
{
    private SchemaParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SchemaParser();
    }

    // ── Filtering ────────────────────────────────────────────────────────────

    public function testSkipsIntrospectionTypes(): void
    {
        $schema = ['__Schema' => $this->objectType('__Schema')];

        $result = iterator_to_array($this->parser->denormalizeSchema($schema));
        $this->assertEmpty($result);
    }

    public function testSkipsScalarTypes(): void
    {
        $schema = ['String' => array_merge($this->objectType('String'), ['kind' => 'SCALAR'])];

        $result = iterator_to_array($this->parser->denormalizeSchema($schema));
        $this->assertEmpty($result);
    }

    public function testYieldsObject(): void
    {
        $schema = ['Product' => $this->objectType('Product')];

        $result = iterator_to_array($this->parser->denormalizeSchema($schema));

        $this->assertArrayHasKey('Product', $result);
        $this->assertSame(TypeKind::OBJECT, $result['Product']->kind);
        $this->assertSame('Product', $result['Product']->name);
    }

    public function testYieldsEnum(): void
    {
        $schema = [
            'Status' => [
                'name' => 'Status', 'kind' => 'ENUM', 'fields' => null, 'inputFields' => null,
                'possibleTypes' => null, 'interfaces' => null, 'description' => null, 'ofType' => null,
                'enumValues' => [
                    ['name' => 'ACTIVE', 'description' => 'Is active'],
                    ['name' => 'INACTIVE', 'description' => null],
                ],
            ],
        ];

        $result = iterator_to_array($this->parser->denormalizeSchema($schema));

        $this->assertArrayHasKey('Status', $result);
        $this->assertSame(TypeKind::ENUM, $result['Status']->kind);
        $this->assertCount(2, $result['Status']->enumValues ?? []);
        $this->assertSame('ACTIVE', $result['Status']->enumValues[0]->name);
    }

    public function testYieldsInputObject(): void
    {
        $schema = [
            'CreateInput' => [
                'name' => 'CreateInput', 'kind' => 'INPUT_OBJECT', 'fields' => null,
                'possibleTypes' => null, 'interfaces' => null, 'description' => null, 'ofType' => null,
                'enumValues' => null,
                'inputFields' => [
                    [
                        'name' => 'title', 'isDeprecated' => false, 'deprecationReason' => null,
                        'description' => null, 'defaultValue' => null,
                        'type' => ['kind' => 'SCALAR', 'name' => 'String', 'ofType' => null],
                    ],
                ],
            ],
        ];

        $result = iterator_to_array($this->parser->denormalizeSchema($schema));

        $this->assertArrayHasKey('CreateInput', $result);
        $type = $result['CreateInput'];
        $this->assertCount(1, $type->inputFields ?? []);
        $this->assertSame('title', $type->inputFields[0]->name);
    }

    public function testObjectFieldsAreDenormalized(): void
    {
        $schema = [
            'Product' => [
                'name' => 'Product', 'kind' => 'OBJECT', 'inputFields' => null,
                'possibleTypes' => null, 'interfaces' => [], 'description' => null, 'ofType' => null,
                'enumValues' => null,
                'fields' => [
                    [
                        'name' => 'title', 'isDeprecated' => false, 'deprecationReason' => null,
                        'description' => 'Product title',
                        'type' => ['kind' => 'SCALAR', 'name' => 'String', 'ofType' => null],
                    ],
                ],
            ],
        ];

        $result = iterator_to_array($this->parser->denormalizeSchema($schema));
        $product = $result['Product'];

        $fields = $product->fields ?? [];
        $this->assertCount(1, $fields);
        $firstField = $fields[0] ?? null;
        $this->assertNotNull($firstField);
        $this->assertSame('title', $firstField->name);
        $this->assertSame('Product title', $firstField->description);
    }

    public function testReservedTypeNameIsEscaped(): void
    {
        $schema = ['list' => $this->objectType('list')];

        $result = iterator_to_array($this->parser->denormalizeSchema($schema));

        $this->assertArrayHasKey('list', $result);
        $this->assertSame('listType', $result['list']->name);
    }

    public function testOfTypeIsRecursivelyDenormalized(): void
    {
        $schema = [
            'Product' => [
                'name' => 'Product', 'kind' => 'OBJECT', 'inputFields' => null,
                'possibleTypes' => null, 'interfaces' => [], 'description' => null, 'enumValues' => null,
                'fields' => [
                    [
                        'name' => 'tags', 'isDeprecated' => false, 'deprecationReason' => null,
                        'description' => null,
                        'type' => [
                            'kind' => 'NON_NULL',
                            'name' => null,
                            'ofType' => [
                                'kind' => 'LIST',
                                'name' => null,
                                'ofType' => ['kind' => 'SCALAR', 'name' => 'String', 'ofType' => null],
                            ],
                        ],
                    ],
                ],
                'ofType' => null,
            ],
        ];

        $result = iterator_to_array($this->parser->denormalizeSchema($schema));
        $fields = $result['Product']->fields ?? [];
        $tagsField = $fields[0] ?? null;
        $this->assertNotNull($tagsField);

        $this->assertSame(TypeKind::NON_NULL, $tagsField->type->kind);
        $listType = $tagsField->type->ofType;
        $this->assertNotNull($listType);
        $this->assertSame(TypeKind::LIST, $listType->kind);
        $scalarType = $listType->ofType;
        $this->assertNotNull($scalarType);
        $this->assertSame(TypeKind::SCALAR, $scalarType->kind);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function objectType(string $name): array
    {
        return [
            'name' => $name, 'kind' => 'OBJECT', 'description' => null,
            'fields' => [], 'inputFields' => null, 'possibleTypes' => null,
            'interfaces' => [], 'enumValues' => null, 'ofType' => null,
        ];
    }
}
