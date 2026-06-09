<?php

declare(strict_types=1);

namespace Aksonov\GraphqlGenerator\Tests;

use Aksonov\GraphqlGenerator\Types\PhpFieldType;
use Aksonov\GraphqlGenerator\Types\SchemaTypes\Type;
use Aksonov\GraphqlGenerator\Types\SchemaTypes\TypeKind;
use PHPUnit\Framework\TestCase;

final class PhpFieldTypeTest extends TestCase
{
    private const SCALARS = PhpFieldType::BUILTIN_SCALARS;

    // ── BUILTIN_SCALARS ──────────────────────────────────────────────────────

    public function testBuiltinScalarsMappingsProduceCorrectPhpTypes(): void
    {
        $cases = [
            'Int'     => 'int',
            'Float'   => 'float',
            'String'  => 'string',
            'ID'      => 'string',
            'Boolean' => 'bool',
        ];

        foreach ($cases as $graphql => $expected) {
            $type = $this->makeType(TypeKind::SCALAR, $graphql);
            $result = PhpFieldType::parseGraphQLFieldType($type, PhpFieldType::BUILTIN_SCALARS, false);
            $this->assertSame($expected, $result->name, "Built-in '$graphql' should map to '$expected'");
        }

        // The $cases array above intentionally covers every built-in scalar; if
        // a key is added to BUILTIN_SCALARS without a matching $cases entry the
        // loop will miss it, so keep both lists in sync when updating.
    }

    // ── Scalar lookups ───────────────────────────────────────────────────────

    public function testKnownScalarMapsToPhpType(): void
    {
        $type = $this->makeType(TypeKind::SCALAR, 'DateTime');
        $map = array_merge(self::SCALARS, ['DateTime' => 'string']);

        $result = PhpFieldType::parseGraphQLFieldType($type, $map);

        $this->assertSame('string|null', $result->name);
        $this->assertSame('string|null', $result->doctype);
    }

    public function testUnknownScalarFallsBackToGraphqlName(): void
    {
        $type = $this->makeType(TypeKind::SCALAR, 'MyCustomScalar');

        $result = PhpFieldType::parseGraphQLFieldType($type, self::SCALARS);

        $this->assertSame('MyCustomScalar|null', $result->name);
    }

    public function testMixedTypeIsNotSuffixedWithNull(): void
    {
        $type = $this->makeType(TypeKind::SCALAR, 'JSON');
        $map = array_merge(self::SCALARS, ['JSON' => 'mixed']);

        $result = PhpFieldType::parseGraphQLFieldType($type, $map);

        $this->assertSame('mixed', $result->name);
        $this->assertSame('mixed', $result->doctype);
    }

    // ── NON_NULL ─────────────────────────────────────────────────────────────

    public function testNonNullRemovesNullability(): void
    {
        $inner = $this->makeType(TypeKind::SCALAR, 'String');
        $nonNull = $this->makeType(TypeKind::NON_NULL, null, ofType: $inner);

        $result = PhpFieldType::parseGraphQLFieldType($nonNull, self::SCALARS);

        $this->assertSame('string', $result->name);
    }

    public function testNonNullWithMissingOfTypeThrows(): void
    {
        $nonNull = $this->makeType(TypeKind::NON_NULL, null, ofType: null);

        $this->expectException(\LogicException::class);
        PhpFieldType::parseGraphQLFieldType($nonNull, self::SCALARS);
    }

    // ── LIST ─────────────────────────────────────────────────────────────────

    public function testNullableListYieldsArrayNull(): void
    {
        $inner = $this->makeType(TypeKind::SCALAR, 'String');
        $list = $this->makeType(TypeKind::LIST, null, ofType: $inner);

        $result = PhpFieldType::parseGraphQLFieldType($list, self::SCALARS);

        $this->assertSame('array|null', $result->name);
        $this->assertSame('string[]|null[]|null', $result->doctype);
    }

    public function testNonNullListYieldsArray(): void
    {
        $inner = $this->makeType(TypeKind::SCALAR, 'Int');
        $list = $this->makeType(TypeKind::LIST, null, ofType: $inner);
        $nonNull = $this->makeType(TypeKind::NON_NULL, null, ofType: $list);

        $result = PhpFieldType::parseGraphQLFieldType($nonNull, self::SCALARS);

        $this->assertSame('array', $result->name);
        $this->assertSame('int[]|null[]', $result->doctype);
    }

    public function testListWithMissingOfTypeThrows(): void
    {
        $list = $this->makeType(TypeKind::LIST, null, ofType: null);

        $this->expectException(\LogicException::class);
        PhpFieldType::parseGraphQLFieldType($list, self::SCALARS);
    }

    // ── UNION / INTERFACE ────────────────────────────────────────────────────

    public function testUnionWithPossibleTypesIsJoined(): void
    {
        $a = $this->makeType(TypeKind::OBJECT, 'Cat');
        $b = $this->makeType(TypeKind::OBJECT, 'Dog');
        $union = $this->makeType(TypeKind::UNION, 'Pet', possibleTypes: [$a, $b]);

        $result = PhpFieldType::parseGraphQLFieldType($union, self::SCALARS);

        $this->assertSame('Cat|Dog|null', $result->name);
    }

    public function testUnionDeduplicatesPossibleTypes(): void
    {
        $a = $this->makeType(TypeKind::OBJECT, 'Node');
        $b = $this->makeType(TypeKind::OBJECT, 'Node');
        $union = $this->makeType(TypeKind::UNION, 'Iface', possibleTypes: [$a, $b]);

        $result = PhpFieldType::parseGraphQLFieldType($union, self::SCALARS);

        $this->assertSame('Node|null', $result->name);
    }

    public function testNonNullableUnion(): void
    {
        $a = $this->makeType(TypeKind::OBJECT, 'Cat');
        $b = $this->makeType(TypeKind::OBJECT, 'Dog');
        $union = $this->makeType(TypeKind::UNION, 'Pet', possibleTypes: [$a, $b]);
        $nonNull = $this->makeType(TypeKind::NON_NULL, null, ofType: $union);

        $result = PhpFieldType::parseGraphQLFieldType($nonNull, self::SCALARS);

        $this->assertSame('Cat|Dog', $result->name);
    }

    // ── escape() ─────────────────────────────────────────────────────────────

    public function testEscapeAppendsTypeSuffixForReservedWord(): void
    {
        $this->assertSame('listType', PhpFieldType::escape('list'));
        $this->assertSame('classType', PhpFieldType::escape('class'));
    }

    public function testEscapeIsCaseInsensitive(): void
    {
        $this->assertSame('ListType', PhpFieldType::escape('List'));
    }

    public function testEscapePassthroughForNonReserved(): void
    {
        $this->assertSame('Product', PhpFieldType::escape('Product'));
    }

    public function testEscapeNullReturnsNull(): void
    {
        $this->assertNull(PhpFieldType::escape(null));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @param list<Type>|null $possibleTypes */
    private function makeType(
        TypeKind $kind,
        ?string $name,
        ?Type $ofType = null,
        ?array $possibleTypes = null,
    ): Type {
        return new Type(kind: $kind, name: $name, ofType: $ofType, possibleTypes: $possibleTypes);
    }
}
