<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue;

use JesseGall\CodeCommandments\Vue\Script;
use PHPUnit\Framework\TestCase;

final class ScriptTest extends TestCase
{
    public function test_a_numeric_separator_is_not_read_as_an_identifier(): void
    {
        // The bug this shares a lexer to kill: Script had its OWN copy of the TS lexer whose number
        // arm stopped at `_`, so `1_000` lexed as the number `1` (dropped) plus an IDENTIFIER
        // `_000` — a name that is not in the source, offered to anything asking what this script
        // declares. One lexer, and the separator is part of the literal.
        $script = new Script('const limit = 1_000; const rate = ref<number>(2);');

        $this->assertSame('number', $script->declaredType('rate'), 'the shared lex still reads the rest of the script');
        $this->assertNull($script->declaredType('_000'), '`_000` is not a declaration — it is half of `1_000`');
    }

    public function test_a_function_return_type_does_not_swallow_the_body(): void
    {
        // The bug: `(): Promise<void> { … }` read the BODY `{…}` as an object type, leaking
        // `Promise<void>{returngo();}` into a prop type and breaking the generated component.
        $script = new Script('function save(): Promise<void> { return go(); }');

        $this->assertSame('() => Promise<void>', $script->declaredType('save'));
    }

    public function test_an_arrow_function_return_type_stops_at_the_arrow(): void
    {
        $script = new Script('const load = (): User[] => { return fetchUsers(); };');

        $this->assertSame('() => User[]', $script->declaredType('load'));
    }

    public function test_an_arrow_return_type_led_by_a_verbatim_keyword_stops_at_the_arrow(): void
    {
        // The bug: a return type starting with a VERBATIM keyword (`readonly`/`keyof`) went to the
        // verbatim reader, which consumed the arrow-function's own `=> { … }` and the rest of the
        // module — so `f` (and every later decl) lost its type. At depth 0 the `=>` ENDS the type.
        $script = new Script('const f = (port: NodePortData): readonly WorkflowVariable[] => { return go(); }; const other = 1;');

        $this->assertSame('(port: NodePortData) => readonly WorkflowVariable[]', $script->declaredType('f'));
    }

    public function test_a_function_return_type_led_by_a_verbatim_keyword_does_not_swallow_the_body(): void
    {
        // The bug: `function f(): readonly T[] { … }` — the `readonly`-led return type went to the
        // verbatim reader, which read the body `{ … }` as an object type. A `{` after a COMPLETE type
        // (here `]`) is the body, not the type.
        $script = new Script('function compatibleVariables(port: NodePortData): readonly WorkflowVariable[] { return xs; }');

        $this->assertSame('readonly WorkflowVariable[]', $script->returnTypeName('compatibleVariables'));
        $this->assertSame('(port: NodePortData) => readonly WorkflowVariable[]', $script->declaredType('compatibleVariables'));
    }

    public function test_a_plain_const_type_annotation_is_read(): void
    {
        $script = new Script('const count: number = 0;');

        $this->assertSame('number', $script->declaredType('count'));
    }

    public function test_an_explicit_function_type_annotation_keeps_its_arrow(): void
    {
        // The bug: readType broke a function-type annotation at the arrow's `=`, truncating
        // `(id: string) => void` to `(id:string)` — invalid TS ('=>' expected) in the generated
        // component. The `=>` must be read as part of the type, not an initializer.
        $this->assertSame('(id: string) => void', new Script('const onPick: (id: string) => void = x;')->declaredType('onPick'));
        $this->assertSame('(a: number, b: string) => Promise<void>', new Script('const cb: (a: number, b: string) => Promise<void> = x;')->declaredType('cb'));
    }

    public function test_a_function_typed_prop_keeps_its_arrow(): void
    {
        $props = new Script('defineProps<{ onFoo: (a: number) => void; nested: (x: T) => (y: U) => R; label: string }>()')->propTypes();

        $this->assertSame('(a: number) => void', $props['onFoo']);
        $this->assertSame('(x: T) => (y: U) => R', $props['nested'], 'curried arrows survive');
        $this->assertSame('string', $props['label']);
    }

    public function test_an_inferred_ref_takes_the_type_of_its_initializer_literal(): void
    {
        // The gap behind `busy/importOpen/templatesOpen` extracting as `unknown`: a ref with
        // no generic — TS infers the type, and now so do we, from the literal argument.
        $this->assertSame('boolean', (new Script('const open = ref(false);'))->declaredType('open'));
        $this->assertSame('string', (new Script("const name = ref('');"))->declaredType('name'));
        $this->assertSame('number', (new Script('const total = shallowRef(0);'))->declaredType('total'));
    }

    public function test_a_homogeneous_literal_array_infers_its_element_type(): void
    {
        // `const pageSizes = [50, 100, 200]` extracted as `unknown`; TS widens it to `number[]`, and
        // now so do we — soundly, from a homogeneous primitive-literal array.
        $this->assertSame('number[]', (new Script('const pageSizes = [50, 100, 200];'))->declaredType('pageSizes'));
        $this->assertSame('string[]', (new Script("const names = ['a', 'b'];"))->declaredType('names'));
        $this->assertSame('boolean[]', (new Script('const flags = [true, false];'))->declaredType('flags'));
    }

    public function test_a_non_homogeneous_or_complex_literal_array_stays_unresolved(): void
    {
        // A mixed, object, or empty array is a union / needs a checker — we don't guess.
        $this->assertNull((new Script('const mixed = [1, "a"];'))->declaredType('mixed'));
        $this->assertNull((new Script('const objs = [{ a: 1 }];'))->declaredType('objs'));
        $this->assertNull((new Script('const empty = [];'))->declaredType('empty'));
    }

    public function test_an_explicit_ref_generic_still_wins_over_the_initializer(): void
    {
        $script = new Script('const id = ref<string | null>(null);');

        $this->assertSame('string | null', $script->declaredType('id'));
    }

    public function test_unwrap_ref_peels_a_single_generic_wrapper(): void
    {
        $this->assertSame('User', Script::unwrapRef('Ref<User>'));
        $this->assertSame('string', Script::unwrapRef('ComputedRef<string>'));
        $this->assertSame('number[]', Script::unwrapRef('ShallowRef<number[]>'));
    }

    public function test_unwrap_ref_takes_the_read_side_of_a_writable_ref(): void
    {
        // The modern `Ref<Value, Setter>` two-arg form — the prop takes the getter (first) type.
        $this->assertSame('Foo | null', Script::unwrapRef('Ref<Foo | null, Foo | null>'));
    }

    public function test_unwrap_ref_unwraps_inside_a_union_and_collapses_duplicates(): void
    {
        // The reported #320 shape: a nullable writable ref of a nullable value flattens to `V | null`.
        $this->assertSame(
            'InspectorTexts | null',
            Script::unwrapRef('Ref<InspectorTexts | null, InspectorTexts | null> | null'),
        );
    }

    public function test_unwrap_ref_leaves_a_plain_type_untouched(): void
    {
        $this->assertSame('string', Script::unwrapRef('string'));
        $this->assertSame('number | null', Script::unwrapRef('number | null'));
        $this->assertSame('Foo<Bar>', Script::unwrapRef('Foo<Bar>'));
    }

    public function test_a_non_literal_ref_initializer_stays_unresolved(): void
    {
        // `ref(props.busy)` — only a real type checker could resolve this; we don't guess.
        $this->assertNull((new Script('const busy = ref(props.busy);'))->declaredType('busy'));
        $this->assertNull((new Script('const items = ref([]);'))->declaredType('items'));
    }

    public function test_a_computed_boolean_getter_is_inferred(): void
    {
        // The dominant computed shape: a comparison / logical chain — boolean, no generic.
        $this->assertSame('boolean', (new Script('const isReadOnly = computed(() => schema.value?.readOnly === true);'))->declaredType('isReadOnly'));
        $this->assertSame('boolean', (new Script('const isEmpty = computed(() => a.value.length === 0 && b.value.length === 0);'))->declaredType('isEmpty'));
        $this->assertSame('boolean', (new Script('const hidden = computed(() => !visible.value);'))->declaredType('hidden'));
    }

    public function test_a_computed_with_an_unresolvable_body_stays_unknown(): void
    {
        // The body is a bare member/call — sound inference can't type it, so we don't guess.
        $this->assertNull((new Script('const label = computed(() => schema.value.label);'))->declaredType('label'));
        $this->assertNull((new Script('const rows = computed(() => items.value.map(toRow));'))->declaredType('rows'));
    }

    public function test_a_destructure_names_its_source_call(): void
    {
        $script = new Script('const { step, fields, restore } = useWizardState(base, id);');

        $this->assertSame('useWizardState', $script->destructuredCall('step'));
        $this->assertSame('useWizardState', $script->destructuredCall('restore'));
        $this->assertNull($script->destructuredCall('absent'));
    }

    public function test_a_functions_declared_return_type_is_read(): void
    {
        $arrow = new Script('export const useThing = (x: string): ThingState => impl(x);');
        $fn = new Script('export function useThing(x: string): ThingState { return impl(x); }');

        $this->assertSame('ThingState', $arrow->returnTypeName('useThing'));
        $this->assertSame('ThingState', $fn->returnTypeName('useThing'));
    }

    public function test_a_named_type_field_is_read_and_ref_unwrapped(): void
    {
        $script = new Script('interface WizardState { step: Ref<string>; fields: Ref<Record<string, unknown>>; ready: boolean; }');

        // A field is a Ref in the source but unwraps to its value type at the binding site.
        $this->assertSame('string', $script->fieldType('WizardState', 'step'));
        $this->assertSame('Record<string, unknown>', $script->fieldType('WizardState', 'fields'));
        $this->assertSame('boolean', $script->fieldType('WizardState', 'ready'));
        $this->assertNull($script->fieldType('WizardState', 'missing'));
    }

    public function test_a_method_param_does_not_overwrite_a_same_named_field(): void
    {
        // The interface has BOTH `step: Ref<string>` and `goToStep(step: string)` — the
        // method param must not corrupt the field (the method is consumed whole).
        $script = new Script('interface S { step: Ref<string>; goToStep(step: string): void; reset(): Promise<void>; }');

        $this->assertSame('string', $script->fieldType('S', 'step'));
    }

    public function test_define_props_is_found_inside_with_defaults(): void
    {
        // `withDefaults(defineProps<Props>(), { … })` is a pervasive Vue pattern; the macro is the
        // FIRST argument, not the top-level call. Missing it left every such component's props
        // typed `unknown` after extraction.
        $props = new Script('interface Props { a: string; b?: number | null }' . "\n" . 'const props = withDefaults(defineProps<Props>(), { b: 0 });')->propTypes();

        $this->assertSame(['a' => 'string', 'b' => 'number | null'], $props);
    }

    public function test_an_inferred_composable_return_is_typed_field_by_field(): void
    {
        // useX has NO declared return — its shape is inferred from `return { … }`. Each returned
        // local is typed from the composable's own body (a ref's value, a function's signature),
        // ref-unwrapped, the way a type checker infers it. This is what un-`unknown`s the bulk of
        // composable-derived props.
        $script = new Script(<<<'TS'
            function useTaxTypes() {
                const taxes = ref<TaxTypeData[]>([]);
                function taxRate(taxId: string | null): number { return 0; }
                function formatTaxLabel(tax: TaxTypeData): string { return ''; }
                return { taxes, taxRate, formatTaxLabel };
            }
            TS);

        $this->assertSame(
            ['taxes' => 'TaxTypeData[]', 'taxRate' => '(taxId: string | null) => number', 'formatTaxLabel' => '(tax: TaxTypeData) => string'],
            $script->inferredReturnFields('useTaxTypes'),
        );
    }

    public function test_readonly_as_a_property_name_is_not_eaten_as_a_modifier(): void
    {
        // The bug: `readonly?: boolean` — a property NAMED `readonly` — was parsed as the `readonly`
        // MODIFIER, so the `?` became the property name and the prop surfaced as `'?' => 'boolean'`.
        // `readonly` is a modifier only before a real property name, never before `?`/`:`/`(`.
        $props = new Script('interface Props { readonly?: boolean; disabled?: boolean }' . "\n" . 'const props = defineProps<Props>();')->propTypes();

        $this->assertSame(['readonly' => 'boolean', 'disabled' => 'boolean'], $props);
    }

    public function test_a_readonly_modifier_before_a_real_property_is_still_stripped(): void
    {
        $props = new Script('interface Props { readonly id: string; name: string }' . "\n" . 'const props = defineProps<Props>();')->propTypes();

        $this->assertSame(['id' => 'string', 'name' => 'string'], $props);
    }

    public function test_an_interface_method_is_typed_as_its_signature(): void
    {
        // A destructured composable method is a function prop — typed as its signature.
        $script = new Script('interface S { goToStep(step: string): void; reset(): Promise<void>; save(): void; }');

        $this->assertSame('(step: string) => void', $script->fieldType('S', 'goToStep'));
        $this->assertSame('() => Promise<void>', $script->fieldType('S', 'reset'));
        $this->assertSame('() => void', $script->fieldType('S', 'save'));
    }
}
