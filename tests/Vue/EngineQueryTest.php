<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue;

use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\Expr\Parser;
use PHPUnit\Framework\TestCase;

/**
 * The reusable query predicates the detectors and scribes compose — proven directly, so the
 * engine's surface is guaranteed, not just exercised through a detector.
 */
final class EngineQueryTest extends TestCase
{
    public function test_renders_answers_whether_a_subtree_references_a_tag(): void
    {
        $div = Codebase::fromString('<template><div><Badge>hi</Badge></div></template>')->whereTag('div')->first();

        $this->assertTrue($div->renders('Badge'));
        $this->assertFalse($div->renders('Alert'));
        $this->assertFalse($div->renders('Badg'), 'a prefix is not a tag match');
    }

    public function test_is_template_distinguishes_the_fragment_wrapper(): void
    {
        $codebase = Codebase::fromString('<template><template v-if="x"><span>a</span></template></template>');

        $this->assertTrue($codebase->whereTag('template')->first()->isTemplate());
        $this->assertFalse($codebase->whereTag('span')->first()->isTemplate());
    }

    public function test_substantial_requires_content_and_internal_structure(): void
    {
        // 6 elements, 3 levels deep — a real component.
        $deep = Codebase::fromString('<template><section><div><p>a</p><p>b</p><span>c</span><em>d</em></div></section></template>')
            ->whereTag('section')->first();
        // flat + tiny — better left inline.
        $thin = Codebase::fromString('<template><div><span>a</span></div></template>')->whereTag('div')->first();

        $this->assertTrue($deep->substantial());
        $this->assertFalse($thin->substantial());
    }

    public function test_nested_deeper_than_selects_the_deep_substantial_nodes(): void
    {
        $shallow = Codebase::fromString('<template><div><p>flat</p></div></template>');
        $this->assertSame([], $shallow->whereElement()->nestedDeeperThan(8, 3)->get());

        // Deep enough that a node exists BOTH >8 levels in AND with >3 levels still below it.
        $deep = Codebase::fromString('<template>' . str_repeat('<div>', 14) . 'x' . str_repeat('</div>', 14) . '</template>');
        $this->assertNotSame([], $deep->whereElement()->nestedDeeperThan(8, 3)->get());
    }

    public function test_source_omitting_removes_a_directive_by_its_span(): void
    {
        $source = '<template><div v-if="open" class="card" :title="t">x</div></template>';
        $div = Codebase::fromString($source)->whereTag('div')->first();

        // The write engine renders the element source without v-if — by its KNOWN span,
        // swallowing the space before it, keeping every other attribute. No regex.
        $written = $div->sourceOmitting($source, $div->start, $div->end, ['v-if']);

        $this->assertStringContainsString('<div class="card" :title="t">', $written);
        $this->assertStringNotContainsString('v-if', $written);
        $this->assertStringNotContainsString('  ', $written, 'no double-space gap left behind');
    }

    public function test_source_omitting_leaves_a_directive_outside_the_slice_untouched(): void
    {
        // A directive carried OUT to a call site (on a wrapper outside the content slice) is
        // not in [from, to), so it survives — the boundary between component and call site.
        $source = '<template><template v-if="open"><div class="card">x</div></template></template>';
        $wrapper = Codebase::fromString($source)->whereTag('template')->first();
        $inner = $wrapper->children[0]; // the <div> content

        $written = $wrapper->sourceOmitting($source, $inner->start, $inner->end, ['v-if']);

        $this->assertStringContainsString('v-if="open"', $source);
        $this->assertSame('<div class="card">x</div>', $written, 'only the content slice, directive untouched');
    }

    public function test_sfc_elements_scopes_a_query_to_one_component_subtree(): void
    {
        $sfc = Codebase::fromString(
            '<template><form><input v-model="form.name"><input v-model.lazy="form.email"><span>x</span></form></template>'
        )->components()[0];

        // The subtree query composes the same DSL the codebase opens — here finding every
        // element carrying a v-model FAMILY directive (plain, arg, or modifier).
        $bound = $sfc->elements()->withDirectiveFamily(\JesseGall\CodeCommandments\Vue\Directive::Model)->get();

        $this->assertCount(2, $bound, 'both v-model and v-model.lazy match the family');
        $this->assertSame(['input', 'input'], array_map(static fn ($m): string => $m->tag, $bound));
    }

    public function test_parse_for_reads_a_v_for_binding_off_the_token_stream(): void
    {
        $bare = Parser::parseFor('item in items');
        $this->assertSame(['item'], $bare->get('aliases'));
        $this->assertSame('in', $bare->get('keyword'));
        $this->assertSame('items', $bare->get('iterable')->source());

        // Grouping parens collect their names; the `of` keyword is honoured; the iterable
        // is a real member expression, not a string.
        $indexed = Parser::parseFor('(chart, i) of group.charts');
        $this->assertSame(['chart', 'i'], $indexed->get('aliases'));
        $this->assertSame('of', $indexed->get('keyword'));
        $this->assertSame('group.charts', $indexed->get('iterable')->source());

        // A destructuring pattern is a binding, not a usable loop-var name — its inner names
        // are skipped, the trailing index survives.
        $destructured = Parser::parseFor('({ id, name }, index) in rows');
        $this->assertSame(['index'], $destructured->get('aliases'));
    }

    public function test_prop_bindings_returns_only_bound_props_as_expressions(): void
    {
        $child = Codebase::fromString('<template><Child :total="order.sum" label="x" @click="go" v-if="ok"/></template>')
            ->whereTag('Child')->first();

        $bindings = $child->propBindings();

        $this->assertSame(['total'], array_keys($bindings), 'static attr, event and directive excluded');
        $this->assertSame('order.sum', $bindings['total']->source());
    }

    public function test_object_entries_pairs_each_key_with_its_value(): void
    {
        $object = Parser::parse("{ '@x': resolve(s, 'x'), n: 1, [computed]: 2 }");
        $entries = $object->objectEntries();

        $this->assertSame(['@x', 'n'], array_keys($entries), 'string + identifier keys kept; computed dropped');
        $this->assertSame('resolve', $entries['@x']->get('callee')->get('name'));
        $this->assertSame('1', $entries['n']->get('raw'));
    }

    public function test_expr_as_chain_returns_a_pure_member_path_or_null(): void
    {
        $this->assertSame(['order', 'customer', 'name'], Parser::parse('order.customer.name')->asChain());
        $this->assertSame(['user'], Parser::parse('user')->asChain());
        $this->assertNull(Parser::parse('order.total()')->asChain(), 'a call is not a pure chain');
        $this->assertNull(Parser::parse('items[0]')->asChain(), 'an index is not a pure chain');
        $this->assertNull(Parser::parse('a + b')->asChain(), 'an operator is not a pure chain');
    }
}
