<?php

declare(strict_types=1);

namespace Aksonov\GraphqlGenerator\Types\SchemaTypes;

class Field
{
    public function __construct(
        public string $name,
        public bool $isDeprecated,
        public ?string $deprecationReason,
        public ?string $description,
        public Type $type,
    ) {
    }
}
