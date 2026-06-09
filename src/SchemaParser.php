<?php

declare(strict_types=1);

namespace Aksonov\GraphqlGenerator;

use Aksonov\GraphqlGenerator\Types\PhpFieldType;
use Aksonov\GraphqlGenerator\Types\SchemaTypes\EnumValue;
use Aksonov\GraphqlGenerator\Types\SchemaTypes\Field;
use Aksonov\GraphqlGenerator\Types\SchemaTypes\InputField;
use Aksonov\GraphqlGenerator\Types\SchemaTypes\Type;
use Aksonov\GraphqlGenerator\Types\SchemaTypes\TypeKind;

final class SchemaParser
{
    /**
     * @param array<string, array<string, mixed>> $schema
     * @return iterable<string, Type>
     */
    public function denormalizeSchema(array $schema): iterable
    {
        foreach ($schema as $type) {
            $name = isset($type['name']) && is_string($type['name']) ? $type['name'] : null;
            $kind = isset($type['kind']) && is_string($type['kind']) ? $type['kind'] : null;

            if (
                $name !== null
                && $kind !== null
                && !str_starts_with($name, '__')
                && in_array($kind, [
                    TypeKind::OBJECT->value,
                    TypeKind::INTERFACE->value,
                    TypeKind::INPUT_OBJECT->value,
                    TypeKind::ENUM->value
                ])
            ) {
                yield $name => $this->denormalizeType($type, true);
            }
        }
    }

    /**
     * @param array<string, mixed> $type
     */
    private function denormalizeType(array $type, bool $top = false): Type
    {
        $kind = isset($type['kind']) && is_string($type['kind']) ? $type['kind'] : '';
        $typeKind = TypeKind::from($kind);

        $name = isset($type['name']) && is_string($type['name']) ? $type['name'] : null;
        $description = isset($type['description']) && is_string($type['description'])
            ? $type['description']
            : null;

        $fields = isset($type['fields']) && is_array($type['fields'])
            ? array_map(fn (mixed $f): Field => $this->denormalizeField($this->toStringKeyedArray($f)), $type['fields'])
            : null;

        $inputFields = isset($type['inputFields']) && is_array($type['inputFields'])
            ? array_map(
                fn (mixed $f): InputField => $this->denormalizeInputField($this->toStringKeyedArray($f)),
                $type['inputFields']
            )
            : null;

        $possibleTypes = isset($type['possibleTypes']) && is_array($type['possibleTypes'])
            ? array_map(
                fn (mixed $t): Type => $this->denormalizeType($this->toStringKeyedArray($t)),
                $type['possibleTypes']
            )
            : null;

        $interfaces = isset($type['interfaces']) && is_array($type['interfaces'])
            ? array_map(
                fn (mixed $t): Type => $this->denormalizeType($this->toStringKeyedArray($t)),
                $type['interfaces']
            )
            : null;

        $enumValues = isset($type['enumValues']) && is_array($type['enumValues'])
            ? array_map(
                fn (mixed $e): EnumValue => $this->denormalizeEnum($this->toStringKeyedArray($e)),
                $type['enumValues']
            )
            : null;

        $ofType = isset($type['ofType']) && is_array($type['ofType'])
            ? $this->denormalizeType($this->toStringKeyedArray($type['ofType']))
            : null;

        return new Type(
            kind: $typeKind,
            name: PhpFieldType::escape($name),
            description: $description,
            fields: $fields,
            inputFields: $inputFields,
            possibleTypes: $possibleTypes,
            interfaces: $interfaces,
            enumValues: $enumValues,
            ofType: $ofType,
        );
    }

    /**
     * @param array<string, mixed> $enum
     */
    private function denormalizeEnum(array $enum): EnumValue
    {
        $name = isset($enum['name']) && is_string($enum['name']) ? $enum['name'] : '';
        $description = isset($enum['description']) && is_string($enum['description'])
            ? $enum['description']
            : null;

        return new EnumValue(
            name: $name,
            description: $description,
        );
    }

    /**
     * @param array<string, mixed> $field
     */
    private function denormalizeField(array $field): Field
    {
        $name = isset($field['name']) && is_string($field['name']) ? $field['name'] : '';
        $isDeprecated = isset($field['isDeprecated']) && is_bool($field['isDeprecated'])
            ? $field['isDeprecated']
            : false;
        $deprecationReason = isset($field['deprecationReason']) && is_string($field['deprecationReason'])
            ? $field['deprecationReason']
            : null;
        $description = isset($field['description']) && is_string($field['description'])
            ? $field['description']
            : null;

        if (!isset($field['type']) || !is_array($field['type'])) {
            throw new \RuntimeException("Field '$name' is missing a type.");
        }

        return new Field(
            name: $name,
            isDeprecated: $isDeprecated,
            deprecationReason: $deprecationReason,
            description: $description,
            type: $this->denormalizeType($this->toStringKeyedArray($field['type']))
        );
    }

    /**
     * @param array<string, mixed> $field
     */
    private function denormalizeInputField(array $field): InputField
    {
        $name = isset($field['name']) && is_string($field['name']) ? $field['name'] : '';
        $isDeprecated = isset($field['isDeprecated']) && is_bool($field['isDeprecated'])
            ? $field['isDeprecated']
            : false;
        $deprecationReason = isset($field['deprecationReason']) && is_string($field['deprecationReason'])
            ? $field['deprecationReason']
            : null;
        $description = isset($field['description']) && is_string($field['description'])
            ? $field['description']
            : null;

        if (!isset($field['type']) || !is_array($field['type'])) {
            throw new \RuntimeException("Input field '$name' is missing a type.");
        }

        return new InputField(
            name: $name,
            isDeprecated: $isDeprecated,
            deprecationReason: $deprecationReason,
            description: $description,
            type: $this->denormalizeType($this->toStringKeyedArray($field['type'])),
            defaultValue: $field['defaultValue'] ?? null
        );
    }

    /**
     * Narrows an array with unknown key types to string-keyed.
     * JSON/YAML objects always have string keys; this helper makes that explicit to the type checker.
     *
     * @param mixed $value
     * @return array<string, mixed>
     */
    private function toStringKeyedArray(mixed $value): array
    {
        if (!is_array($value)) {
            throw new \RuntimeException('Expected an array, got ' . gettype($value));
        }
        /** @var array<string, mixed> $value */
        return $value;
    }
}
