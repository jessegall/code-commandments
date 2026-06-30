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
}
