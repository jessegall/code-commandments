<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue\Ts;

use JesseGall\CodeCommandments\Vue\Ts\Node\FunctionType;
use JesseGall\CodeCommandments\Vue\Ts\Node\ObjectType;
use JesseGall\CodeCommandments\Vue\Ts\Node\VerbatimType;
use JesseGall\CodeCommandments\Vue\Ts\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The parser is the "can't fail" foundation: every type the grammar models re-renders faithfully,
 * and everything it doesn't is preserved VERBATIM — never truncated. Plus the module facts the
 * Script facade is rebuilt on: declarations, macro type-args, destructured composable callees.
 */
final class ParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function types(): iterable
    {
        yield 'function type' => ['(id: string) => void'];
        yield 'optional + rest params' => ['(a: number, b?: string, ...rest: T[]) => R'];
        yield 'curried arrow' => ['(x: T) => (y: U) => R'];
        yield 'generic return' => ['(a: number) => Promise<void>'];
        yield 'union' => ['string | number | null'];
        yield 'intersection' => ['A & B & C'];
        yield 'array' => ['User[]'];
        yield 'parenthesised array' => ['(A | B)[]'];
        yield 'indexed access' => ["Order['customer']"];
        yield 'nested indexed access' => ["Root['a']['b']"];
        yield 'generic' => ['Ref<User[]>'];
        yield 'nested generic' => ['Map<string, Ref<User>>'];
        yield 'tuple' => ['[string, number]'];
        yield 'literal union' => ["'a' | 'b' | 'c'"];
        yield 'negative literal' => ['-1 | 0 | 1'];
        yield 'typeof' => ['typeof schema'];
        yield 'qualified name' => ['App.Http.View.WarehouseShowPage'];
        yield 'object with method' => ['{ a: string; m(id: number): R }'];
    }

    #[DataProvider('types')]
    public function test_a_modelled_type_re_renders_faithfully(string $source): void
    {
        $this->assertSame($this->normalise($source), $this->normalise(Parser::type($source)->render()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function verbatimTypes(): iterable
    {
        yield 'conditional' => ['A extends B ? X : Y'];
        yield 'mapped' => ['{ [K in keyof T]: T[K] }'];
        yield 'index signature' => ['{ [key: string]: number }'];
        yield 'keyof' => ['keyof WarehouseShowPage'];
        yield 'readonly array' => ['readonly string[]'];
    }

    #[DataProvider('verbatimTypes')]
    public function test_an_unmodelled_type_is_preserved_verbatim(string $source): void
    {
        $type = Parser::type($source);

        $this->assertInstanceOf(VerbatimType::class, $type);
        $this->assertSame($source, $type->render(), 'preserved exactly, never truncated');
    }

    public function test_function_type_keeps_its_arrow_and_return(): void
    {
        $type = Parser::type('(id: string) => void');

        $this->assertInstanceOf(FunctionType::class, $type);
        $this->assertSame('void', $type->returnType->render());
        $this->assertSame('id', $type->params[0]->name);
    }

    public function test_defineprops_type_argument_is_a_real_object_type(): void
    {
        $module = Parser::module('const props = defineProps<{ items: Row[]; onPick: (id: string) => void; label?: string }>();');
        $shape = $module->call('defineProps')?->firstTypeArgument();

        $this->assertInstanceOf(ObjectType::class, $shape);
        $this->assertSame(
            ['items' => 'Row[]', 'onPick' => '(id: string) => void', 'label' => 'string'],
            $shape->fields(),
        );
    }

    public function test_interface_and_type_alias_fields(): void
    {
        $module = Parser::module('interface Row { id: number; name: string } type Point = { x: number; y: number };');

        $this->assertSame(['id' => 'number', 'name' => 'string'], $module->interface('Row')?->fields());
        $this->assertSame(['x' => 'number', 'y' => 'number'], $module->typeAlias('Point')?->fields());
    }

    public function test_destructured_composable_exposes_its_callee(): void
    {
        $module = Parser::module('const { taxes, taxRate } = useTaxTypes();');
        $decl = $module->variable('taxes');

        $this->assertSame('useTaxTypes', $decl?->initCall?->callee);
        $this->assertSame('taxes', $decl?->pattern->keyFor('taxes'));
    }

    public function test_reactive_initializer_is_captured_as_a_call(): void
    {
        $module = Parser::module("const open = ref(false); const total = shallowRef(0);");

        $this->assertSame('ref', $module->variable('open')?->initCall?->callee);
        $this->assertSame('false', $module->variable('open')?->initCall?->arguments[0] ?? null);
    }

    public function test_function_declaration_signature(): void
    {
        $module = Parser::module('function handleSelect(pickList: DashboardPickListData): void { doThing(); }');

        $this->assertSame('(pickList: DashboardPickListData) => void', $module->functionNamed('handleSelect')?->signature()->render());
    }

    public function test_imports_map_locals_to_their_source_member(): void
    {
        $module = Parser::module(
            'import Page from "@/Page.vue"; import { ref, computed as c } from "vue"; import * as z from "zod"; import WarehouseShowPage = App.Http.View.WarehouseShowPage;',
        );

        $sources = [];

        foreach ($module->imports as $import) {
            $sources[] = $import->bindings;
        }

        $this->assertContains(['Page' => 'default'], $sources);
        $this->assertContains(['ref' => 'ref', 'c' => 'computed'], $sources);
        $this->assertContains(['z' => '*'], $sources);
        $this->assertContains(['WarehouseShowPage' => 'App.Http.View.WarehouseShowPage'], $sources);
    }

    public function test_local_names_span_every_binding_form(): void
    {
        $module = Parser::module('const a = 1; const { b, c: d } = x; const [e] = y; function f() {}');

        $this->assertSame(['a', 'b', 'd', 'e', 'f'], $module->localNames());
    }

    public function test_the_parser_is_total_on_gnarly_input(): void
    {
        // No exception, whatever we throw at it — the point of the verbatim floor.
        $module = Parser::module(<<<'TS'
            import { ref } from 'vue';
            type Cond<T> = T extends string ? 1 : 0;
            const x = computed(() => a?.b ?? c);
            const handler = (e: Event) => { e.preventDefault(); };
            watch(() => props.value, (v) => emit('change', v));
            const props = defineProps<{ rows: Record<string, unknown>; cb: (a: number) => void }>();
            TS);

        // It parsed without throwing (the point), and still recovered the real facts.
        $shape = $module->call('defineProps')?->firstTypeArgument();
        $this->assertInstanceOf(ObjectType::class, $shape);
        $this->assertSame('(a: number) => void', $shape->fields()['cb']);
        $this->assertSame('computed', $module->variable('x')?->initCall?->callee);
    }

    public function test_it_is_total_on_an_object_type_with_an_index_signature_and_arrow(): void
    {
        // Regression: the `=>` arrow's `>` corrupted the verbatim reader's depth count, so the
        // fallback ran off the end and the parser looped to OOM (found on a real defineSlots).
        $module = Parser::module('defineSlots<{ default(props: { tab: string }): any; [key: string]: (props?: any) => any; }>();');

        $this->assertCount(1, $module->body);
        $this->assertSame('defineSlots', $module->body[0]->callee ?? null);
    }

    private function normalise(string $type): string
    {
        return (string) preg_replace('/\s+/', ' ', trim($type));
    }
}
