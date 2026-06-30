<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue;

use JesseGall\CodeCommandments\Vue\Script;
use PHPUnit\Framework\TestCase;

final class ScriptTest extends TestCase
{
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

    public function test_a_plain_const_type_annotation_is_read(): void
    {
        $script = new Script('const count: number = 0;');

        $this->assertSame('number', $script->declaredType('count'));
    }

    public function test_an_inferred_ref_takes_the_type_of_its_initializer_literal(): void
    {
        // The gap behind `busy/importOpen/templatesOpen` extracting as `unknown`: a ref with
        // no generic — TS infers the type, and now so do we, from the literal argument.
        $this->assertSame('boolean', (new Script('const open = ref(false);'))->declaredType('open'));
        $this->assertSame('string', (new Script("const name = ref('');"))->declaredType('name'));
        $this->assertSame('number', (new Script('const total = shallowRef(0);'))->declaredType('total'));
    }

    public function test_an_explicit_ref_generic_still_wins_over_the_initializer(): void
    {
        $script = new Script('const id = ref<string | null>(null);');

        $this->assertSame('string|null', $script->declaredType('id'));
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
        $this->assertSame('Record<string,unknown>', $script->fieldType('WizardState', 'fields'));
        $this->assertSame('boolean', $script->fieldType('WizardState', 'ready'));
        $this->assertNull($script->fieldType('WizardState', 'missing'));
    }

    public function test_a_method_param_does_not_overwrite_a_same_named_field(): void
    {
        // The interface has BOTH `step: Ref<string>` and `goToStep(step: string)` — the
        // method param must not corrupt the field. (Methods are skipped, not read as fields.)
        $script = new Script('interface S { step: Ref<string>; goToStep(step: string): void; reset(): Promise<void>; }');

        $this->assertSame('string', $script->fieldType('S', 'step'));
        $this->assertNull($script->fieldType('S', 'goToStep'), 'a method is not a data field');
    }
}
