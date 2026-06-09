<?php

declare(strict_types=1);

namespace Aksonov\GraphqlGenerator\Types\SchemaTypes;

enum TypeKind: string
{
    case SCALAR = 'SCALAR';
    case OBJECT = 'OBJECT';

    case INTERFACE = 'INTERFACE';
    case UNION = 'UNION';

    case ENUM = 'ENUM';
    case INPUT_OBJECT = 'INPUT_OBJECT';

    case LIST = 'LIST';
    case NON_NULL = 'NON_NULL';
}
