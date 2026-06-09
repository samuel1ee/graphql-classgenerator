<?php

declare(strict_types=1);

namespace Aksonov\GraphqlGenerator;

use Aksonov\GraphqlGenerator\Types\PhpFieldType;
use Aksonov\GraphqlGenerator\Types\SchemaTypes\Type;
use Aksonov\GraphqlGenerator\Types\SchemaTypes\TypeKind;
use Aksonov\GraphqlGenerator\Utils\DescriptionProcessor;

final class FileWriter
{
    /**
     * @param array<string, string> $scalarMap  GraphQL scalar name → PHP type string
     */
    public function typeToClass(string $namespace, Type $type, array $scalarMap): string
    {
        $classContent = "<?php\n\ndeclare(strict_types=1);\n\n";
        $classContent .= "namespace $namespace;\n\n";
        if ($type->description) {
            $classContent .= "/**\n";
            foreach (DescriptionProcessor::processDescription(trim($type->description), 110) as $line) {
                $classContent .= "* $line\n";
            }
        }

        return match ($type->kind) {
            TypeKind::ENUM => $classContent . $this->getEnumContent($type),
            TypeKind::OBJECT => $classContent . $this->getClassContent($type, $scalarMap),
            TypeKind::INTERFACE =>  $classContent . $this->getInterfaceContent($type, $scalarMap),
            TypeKind::INPUT_OBJECT => $classContent . $this->getInputObjectContent($type, $scalarMap),
            default => '',
        };
    }

    public function emptyDir(string $outDir): void
    {
        if (!is_dir($outDir)) {
            mkdir($outDir, 0755, true);
            return;
        }

        $iterator = new \DirectoryIterator($outDir);
        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isDot()) {
                continue;
            }
            if ($fileinfo->isDir()) {
                $this->emptyDir($fileinfo->getPathname());
                rmdir($fileinfo->getPathname());
                continue;
            }
            if ($fileinfo->isFile()) {
                unlink($fileinfo->getPathname());
            }
        }
    }

    /**
     * @param array<string, string> $scalarMap
     */
    private function getClassContent(Type $type, array $scalarMap): string
    {
        $classContent = "*/\n";

        $implements = '';
        if ($type->interfaces) {
            $names = array_map(fn($t) => $t->name, $type->interfaces);
            $implements = ' implements ' . implode(', ', $names);
        }

        $classContent .= "class {$type->name}{$implements}\n{\n";

        foreach ($type->fields ?? [] as $field) {
            $fieldType = PhpFieldType::parseGraphQLFieldType($field->type, $scalarMap);

            if ($field->isDeprecated) {
                $classContent .= "    #[\Deprecated(message: \"{$field->deprecationReason}\")]\n";
            }
            $classContent .= "    /** @var {$fieldType->doctype} \$$field->name */\n";
            $classContent .= "    public {$fieldType->name} \$$field->name;\n";
        }
        $classContent .= "}\n";

        return $classContent;
    }

    private function getEnumContent(Type $type): string
    {
        $classContent = "*/\n";
        $classContent .= "enum {$type->name}: string\n{\n";

        foreach ($type->enumValues as $enumValue) {
            if ($enumValue->description) {
                $classContent .= "    /**\n";
                foreach (DescriptionProcessor::processDescription(trim($enumValue->description)) as $line) {
                    $classContent .= "    * $line\n";
                }
                $classContent .= "    */\n";
            }
            $classContent .= "    case {$enumValue->name} = '{$enumValue->name}';\n";
        }
        $classContent .= "}\n";

        return $classContent;
    }

    /**
     * @param array<string, string> $scalarMap
     */
    private function getInputObjectContent(Type $type, array $scalarMap): string
    {
        $docBlock = '';
        $props = '';

        foreach ($type->inputFields ?? [] as $field) {
            $fieldType = PhpFieldType::parseGraphQLFieldType($field->type, $scalarMap);
            $docBlock .= "    * @param {$fieldType->doctype} \$$field->name\n";
            $props .= "        public {$fieldType->name} \$$field->name,\n";
        }
        $classContent = "*/\n";
        $classContent .= "class {$type->name}\n{\n    /**\n";
        $classContent .= $docBlock;
        $classContent .= "    */\n    public function __construct(\n";
        $classContent .= $props;
        $classContent .= "    ) {\n        // ¯\_(ツ)_/¯\n    }\n}\n";

        return $classContent;
    }

    /**
     * @param array<string, string> $scalarMap
     */
    private function getInterfaceContent(Type $type, array $scalarMap): string
    {
        $classContent = "\n";
        foreach ($type->fields ?? [] as $field) {
            $fieldType = PhpFieldType::parseGraphQLFieldType($field->type, $scalarMap);

            $classContent .= " * @property {$fieldType->name} \$$field->name;\n";
        }

        $classContent .= "*/\ninterface {$type->name}\n{\n";
        $classContent .= "}\n";

        return $classContent;
    }
}
