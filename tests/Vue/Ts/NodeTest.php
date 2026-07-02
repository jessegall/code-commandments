<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue\Ts;

use JesseGall\CodeCommandments\Vue\Ts\Node\ArrayType;
use JesseGall\CodeCommandments\Vue\Ts\Node\CompositeType;
use JesseGall\CodeCommandments\Vue\Ts\Node\FunctionType;
use JesseGall\CodeCommandments\Vue\Ts\Node\IndexedAccessType;
use JesseGall\CodeCommandments\Vue\Ts\Node\KeywordType;
use JesseGall\CodeCommandments\Vue\Ts\Node\LiteralType;
use JesseGall\CodeCommandments\Vue\Ts\Node\Method;
use JesseGall\CodeCommandments\Vue\Ts\Node\NamedType;
use JesseGall\CodeCommandments\Vue\Ts\Node\ObjectType;
use JesseGall\CodeCommandments\Vue\Ts\Node\Param;
use JesseGall\CodeCommandments\Vue\Ts\Node\Property;
use PHPUnit\Framework\TestCase;

/**
 * The AST node layer on its own — render() emits valid TS and references() reports the named types a
 * type depends on (the hook for carrying a local type into an extracted child). Independent of the
 * parser: nodes are constructed by hand.
 */
final class NodeTest extends TestCase
{
    public function test_function_type_renders_with_its_arrow(): void
    {
        $fn = new FunctionType(
            [new Param('id', new KeywordType('string')), new Param('opt', new KeywordType('number'), optional: true)],
            new KeywordType('void'),
        );

        $this->assertSame('(id: string, opt?: number) => void', $fn->render());
    }

    public function test_named_type_renders_generics_and_reports_references(): void
    {
        $type = new NamedType('Ref', [new ArrayType(new NamedType('User'))]);

        $this->assertSame('Ref<User[]>', $type->render());
        $this->assertSame(['Ref', 'User'], $type->references());
    }

    public function test_union_and_indexed_access_render(): void
    {
        $union = new CompositeType('|', [new KeywordType('string'), new LiteralType("'a'"), new KeywordType('null')]);
        $this->assertSame("string | 'a' | null", $union->render());

        $indexed = new IndexedAccessType(new NamedType('Order'), new LiteralType("'customer'"));
        $this->assertSame("Order['customer']", $indexed->render());
        $this->assertSame(['Order'], $indexed->references());
    }

    public function test_object_type_fields_present_a_uniform_name_to_type_map(): void
    {
        $object = new ObjectType([
            new Property('items', new ArrayType(new NamedType('EditableItem'))),
            new Method('onPick', [new Param('id', new KeywordType('string'))], new KeywordType('void')),
            new Property('label', new KeywordType('string')),
        ]);

        $this->assertSame(
            ['items' => 'EditableItem[]', 'onPick' => '(id: string) => void', 'label' => 'string'],
            $object->fields(),
        );
        $this->assertContains('EditableItem', $object->references(), 'a local type a prop depends on is discoverable');
    }
}
