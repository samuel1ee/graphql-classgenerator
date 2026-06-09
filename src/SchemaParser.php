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
    public function denormalizeSchema(array $schema): iterable
    {
        foreach ($schema as $type) {
            if (!str_starts_with($type['name'], '__')
                && in_array($type['kind'], [
                    TypeKind::OBJECT->value,
                    TypeKind::INTERFACE->value,
                    TypeKind::INPUT_OBJECT->value,
                    TypeKind::ENUM->value
                ])
            ) {
                yield $type['name'] => $this->denormalizeType($type, true);
            }
        }
    }

    private function denormalizeType(array $type, bool $top = false): Type
    {
        $typeKind = TypeKind::from($type['kind']);

        return new Type(
            kind: $typeKind,
            name: PhpFieldType::escape($type['name']),
            description: $type['description'] ?? null,
            fields: $type['fields'] ?? null ? array_map([$this, 'denormalizeField'], $type['fields']) : null,
            inputFields: $type['inputFields'] ?? null
                ? array_map([$this, 'denormalizeInputField'], $type['inputFields'])
                : null,
            possibleTypes: $type['possibleTypes'] ?? null
                ? array_map([$this, 'denormalizeType'], $type['possibleTypes'])
                : null,
            interfaces: $type['interfaces'] ?? null
                ? array_map([$this, 'denormalizeType'], $type['interfaces'])
                : null,
            enumValues: $type['enumValues'] ?? null ? array_map([$this, 'denormalizeEnum'], $type['enumValues']) : null,
            ofType: $type['ofType'] ?? null ? $this->denormalizeType($type['ofType']) : null,
        );
    }

    private function denormalizeEnum(array $enum): EnumValue
    {
        return new EnumValue(
            name: $enum['name'],
            description: $enum['description']
        );
    }

    private function denormalizeField(array $field): Field
    {
        return new Field(
            name: $field['name'],
            isDeprecated: $field['isDeprecated'],
            deprecationReason: $field['deprecationReason'] ?? null,
            description: $field['description'],
            type: $this->denormalizeType($field['type'])
        );
    }

    private function denormalizeInputField(array $field): InputField
    {
        return new InputField(
            name: $field['name'],
            isDeprecated: $field['isDeprecated'],
            deprecationReason: $field['deprecationReason'] ?? null,
            description: $field['description'],
            type: $this->denormalizeType($field['type']),
            defaultValue: $field['defaultValue'] ?? null
        );
    }
}
