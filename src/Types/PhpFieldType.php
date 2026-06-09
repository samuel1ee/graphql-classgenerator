<?php

declare(strict_types=1);

namespace Aksonov\GraphqlGenerator\Types;

use Aksonov\GraphqlGenerator\Types\SchemaTypes\Type;
use Aksonov\GraphqlGenerator\Types\SchemaTypes\TypeKind;

class PhpFieldType
{
    public const BUILTIN_SCALARS = [
        'Int'     => 'int',
        'Float'   => 'float',
        'String'  => 'string',
        'ID'      => 'string',
        'Boolean' => 'bool',
    ];

    private const RESERVED = [
        '__halt_compiler',
        'abstract',
        'and',
        'array',
        'as',
        'break',
        'callable',
        'case',
        'catch',
        'class',
        'clone',
        'const',
        'continue',
        'declare',
        'default',
        'do',
        'echo',
        'else',
        'elseif',
        'enddeclare',
        'endfor',
        'endforeach',
        'endif',
        'endswitch',
        'endwhile',
        'extends',
        'final',
        'finally',
        'for',
        'foreach',
        'function',
        'global',
        'goto',
        'if',
        'implements',
        'include',
        'include_once',
        'instanceof',
        'insteadof',
        'interface',
        'isset',
        'list',
        'namespace',
        'new',
        'or',
        'print',
        'private',
        'protected',
        'public',
        'require',
        'require_once',
        'return',
        'static',
        'switch',
        'throw',
        'trait',
        'try',
        'unset',
        'use',
        'var',
        'while',
        'xor'
    ];

    private function __construct(
        public readonly string $name,
        public readonly string $doctype,
    ) {
    }

    /**
     * @param array<string, string> $scalarMap  GraphQL scalar name → PHP type string
     */
    public static function parseGraphQLFieldType(Type $type, array $scalarMap, bool $nullable = true): PhpFieldType
    {
        if ($type->kind === TypeKind::NON_NULL) {
            return self::parseGraphQLFieldType($type->ofType, $scalarMap, false);
        }

        if ($type->kind === TypeKind::LIST) {
            $ofType = self::parseGraphQLFieldType($type->ofType, $scalarMap);

            $name = str_replace('|', '[]|', $ofType->name);

            if ($nullable) {
                return new self(
                    'array|null',
                    $name . '[]|null',
                );
            }

            return new self(
                'array',
                $name . '[]',
            );
        }

        if (($type->kind === TypeKind::UNION || $type->kind === TypeKind::INTERFACE) && $type->possibleTypes) {
            $names = [];
            foreach ($type->possibleTypes as $possibleType) {
                $names[] = self::parseGraphQLFieldType($possibleType, $scalarMap, false)->name;
            }

            $name = implode('|', array_unique($names));

            if ($nullable) {
                $name .= '|null';
            }

            return new self(
                $name,
                $name,
            );
        }

        $name = $scalarMap[$type->name] ?? $type->name;

        if ($nullable && $name !== 'mixed') {
            $name .= '|null';
        }

        return new self(
            $name,
            $name,
        );
    }


    public static function escape(string|null $name): string|null
    {
        if (in_array(strtolower($name ?? ''), self::RESERVED)) {
            return $name . 'Type';
        }
        return $name;
    }
}
