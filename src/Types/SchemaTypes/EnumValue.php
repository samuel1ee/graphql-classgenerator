<?php

declare(strict_types=1);

namespace Aksonov\GraphqlGenerator\Types\SchemaTypes;

final class EnumValue
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
    ) {
    }
}
