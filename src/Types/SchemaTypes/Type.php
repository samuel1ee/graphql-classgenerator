<?php

declare(strict_types=1);

namespace Aksonov\GraphqlGenerator\Types\SchemaTypes;

final class Type
{
    public function __construct(
        public TypeKind $kind,
        public ?string $name,
        public ?string $description = null,
        /** @var Field[] */
        public ?array $fields = null,
        /** @var InputField[] */
        public ?array $inputFields = null,
        /** @var Type[] */
        public ?array $possibleTypes = null,
        /** @var Type[] */
        public ?array $interfaces = null,
        /** @var EnumValue[] */
        public ?array $enumValues = null,
        public ?Type $ofType = null,
    ) {
    }
}
