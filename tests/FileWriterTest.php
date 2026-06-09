<?php

declare(strict_types=1);

namespace Aksonov\GraphqlGenerator\Tests;

use Aksonov\GraphqlGenerator\FileWriter;
use Aksonov\GraphqlGenerator\Types\PhpFieldType;
use Aksonov\GraphqlGenerator\Types\SchemaTypes\EnumValue;
use Aksonov\GraphqlGenerator\Types\SchemaTypes\Field;
use Aksonov\GraphqlGenerator\Types\SchemaTypes\Type;
use Aksonov\GraphqlGenerator\Types\SchemaTypes\TypeKind;
use PHPUnit\Framework\TestCase;

final class FileWriterTest extends TestCase
{
    private FileWriter $writer;

    /** @var array<string, string> */
    private array $scalarMap;

    protected function setUp(): void
    {
        $this->writer = new FileWriter();
        $this->scalarMap = array_merge(PhpFieldType::BUILTIN_SCALARS, [
            'DateTime' => 'string',
            'JSON'     => 'mixed',
        ]);
    }

    // ── ENUM ─────────────────────────────────────────────────────────────────

    public function testGeneratesEnum(): void
    {
        $type = new Type(
            kind: TypeKind::ENUM,
            name: 'Status',
            description: 'Order status.',
            enumValues: [
                new EnumValue('ACTIVE', 'Currently active.'),
                new EnumValue('INACTIVE', null),
            ]
        );

        $output = $this->writer->typeToClass('My\\NS', $type, $this->scalarMap);

        $this->assertStringContainsString('enum Status: string', $output);
        $this->assertStringContainsString("case ACTIVE = 'ACTIVE';", $output);
        $this->assertStringContainsString("case INACTIVE = 'INACTIVE';", $output);
        $this->assertStringContainsString('* Currently active.', $output);
        $this->assertStringContainsString('namespace My\\NS;', $output);
    }

    // ── OBJECT ───────────────────────────────────────────────────────────────

    public function testGeneratesClass(): void
    {
        $stringType = new Type(TypeKind::SCALAR, 'String');
        $type = new Type(
            kind: TypeKind::OBJECT,
            name: 'Product',
            description: 'A product.',
            fields: [
                new Field('title', false, null, 'The title.', $stringType),
            ]
        );

        $output = $this->writer->typeToClass('Shopify', $type, $this->scalarMap);

        $this->assertStringContainsString('class Product', $output);
        $this->assertStringContainsString('public string|null $title;', $output);
        $this->assertStringContainsString('* A product.', $output);
    }

    public function testClassImplementsInterfaces(): void
    {
        $iface = new Type(TypeKind::INTERFACE, 'HasId');
        $type = new Type(
            kind: TypeKind::OBJECT,
            name: 'Product',
            interfaces: [$iface],
        );

        $output = $this->writer->typeToClass('NS', $type, $this->scalarMap);

        $this->assertStringContainsString('class Product implements HasId', $output);
    }

    public function testDeprecatedFieldEmitsAttribute(): void
    {
        $stringType = new Type(TypeKind::SCALAR, 'String');
        $type = new Type(
            kind: TypeKind::OBJECT,
            name: 'Foo',
            fields: [
                new Field('oldName', true, 'Use newName instead.', null, $stringType),
            ]
        );

        $output = $this->writer->typeToClass('NS', $type, $this->scalarMap);

        $this->assertStringContainsString('#[\Deprecated(message: "Use newName instead.")]', $output);
    }

    // ── INPUT_OBJECT ─────────────────────────────────────────────────────────

    public function testGeneratesInputObject(): void
    {
        $stringType = new Type(TypeKind::SCALAR, 'String');

        // Import InputField
        $inputField = new \Aksonov\GraphqlGenerator\Types\SchemaTypes\InputField(
            name: 'title',
            isDeprecated: false,
            deprecationReason: null,
            description: null,
            type: $stringType,
            defaultValue: null,
        );

        $type = new Type(
            kind: TypeKind::INPUT_OBJECT,
            name: 'CreateProductInput',
            inputFields: [$inputField],
        );

        $output = $this->writer->typeToClass('NS', $type, $this->scalarMap);

        $this->assertStringContainsString('class CreateProductInput', $output);
        $this->assertStringContainsString('public function __construct(', $output);
        $this->assertStringContainsString('public string|null $title,', $output);
        $this->assertStringContainsString('@param string|null $title', $output);
    }

    // ── INTERFACE ────────────────────────────────────────────────────────────

    public function testGeneratesInterface(): void
    {
        $stringType = new Type(TypeKind::SCALAR, 'String');
        $type = new Type(
            kind: TypeKind::INTERFACE,
            name: 'HasTitle',
            fields: [new Field('title', false, null, null, $stringType)],
        );

        $output = $this->writer->typeToClass('NS', $type, $this->scalarMap);

        $this->assertStringContainsString('interface HasTitle', $output);
        $this->assertStringContainsString('@property string|null $title', $output);
    }

    // ── emptyDir ─────────────────────────────────────────────────────────────

    public function testEmptyDirCreatesDirectoryIfMissing(): void
    {
        $dir = sys_get_temp_dir() . '/fw_test_' . uniqid();
        $this->assertDirectoryDoesNotExist($dir);

        $this->writer->emptyDir($dir);

        $this->assertDirectoryExists($dir);
        rmdir($dir);
    }

    public function testEmptyDirRemovesExistingFiles(): void
    {
        $dir = sys_get_temp_dir() . '/fw_test_' . uniqid();
        mkdir($dir, 0755, true);
        file_put_contents("$dir/foo.php", '<?php');
        file_put_contents("$dir/bar.php", '<?php');

        $this->writer->emptyDir($dir);

        $this->assertFileDoesNotExist("$dir/foo.php");
        $this->assertFileDoesNotExist("$dir/bar.php");
        $this->assertDirectoryExists($dir);
        rmdir($dir);
    }

    public function testEmptyDirRecursivelyRemovesSubdirectories(): void
    {
        $dir = sys_get_temp_dir() . '/fw_test_' . uniqid();
        $sub = "$dir/sub";
        mkdir($sub, 0755, true);
        file_put_contents("$sub/child.php", '<?php');

        $this->writer->emptyDir($dir);

        $this->assertDirectoryDoesNotExist($sub);
        $this->assertDirectoryExists($dir);
        rmdir($dir);
    }
}
