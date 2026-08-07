<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue;

use JesseGall\CodeCommandments\Vue\Attribute;
use JesseGall\CodeCommandments\Vue\Directive;
use JesseGall\CodeCommandments\Vue\Element;
use JesseGall\CodeCommandments\Vue\Sfc;
use JesseGall\PhpTypes\Option;
use PHPUnit\Framework\TestCase;

final class AttributeTest extends TestCase
{
    public function test_a_valueless_attribute_renders_as_its_bare_name(): void
    {
        $this->assertSame('v-else', new Attribute('v-else', Option::none())->render());
        $this->assertSame('v-if="ok"', new Attribute('v-if', Option::some('ok'))->render());
    }

    public function test_a_run_of_attributes_renders_space_separated(): void
    {
        $rendered = Attribute::renderAll([
            new Attribute('v-for', Option::some('i in items')),
            new Attribute(':key', Option::some('i.id')),
            new Attribute('disabled', Option::none()),
        ]);

        $this->assertSame('v-for="i in items" :key="i.id" disabled', $rendered);
    }

    public function test_an_absent_attribute_has_no_value_and_neither_does_a_valueless_one(): void
    {
        $div = $this->element('<div v-else class="card"></div>');

        $this->assertTrue($div->attribute('class')->isSome());
        $this->assertTrue($div->attribute('v-else')->isNone(), 'a valueless attribute has no value to read');
        $this->assertTrue($div->attribute('v-if')->isNone(), 'an absent attribute has no value either');

        // …and presence is the OTHER question, which is why both still have a home.
        $this->assertTrue($div->hasAttribute(Directive::Else));
        $this->assertFalse($div->hasAttribute(Directive::If));
    }

    public function test_an_element_answers_for_its_own_loop(): void
    {
        $li = $this->element('<li v-for="(row, i) in report.rows" :key="row.id"></li>');

        $this->assertSame(['row', 'i'], $li->loopVars());
        $this->assertSame('row', $li->loopVar()->unwrap(), 'only the FIRST alias is an element');
        $this->assertSame('report.rows', $li->loopIterable()->unwrap()->source());
    }

    public function test_an_element_that_is_not_a_loop_has_no_loop(): void
    {
        $div = $this->element('<div class="card"></div>');

        $this->assertTrue($div->loop()->isNone());
        $this->assertSame([], $div->loopVars());
        $this->assertTrue($div->loopVar()->isNone());
        $this->assertTrue($div->loopIterable()->isNone());
    }

    public function test_a_loop_carries_its_structural_directives_and_its_key(): void
    {
        $li = $this->element('<li v-for="i in items" :key="i.id" class="row"></li>');

        $this->assertSame('v-for="i in items" :key="i.id"', Attribute::renderAll($li->carriedDirectives()));
        $this->assertTrue(Attribute::anyNamed($li->carriedDirectives(), Directive::For));
    }

    public function test_a_branch_carries_its_directive_without_a_key(): void
    {
        $div = $this->element('<div v-else :key="k"></div>');

        // No `v-for`, so the `:key` is the element's own business and stays put.
        $this->assertSame('v-else', Attribute::renderAll($div->carriedDirectives()));
        $this->assertFalse(Attribute::anyNamed($div->carriedDirectives(), Directive::For));
    }

    private function element(string $markup): Element
    {
        $root = Sfc::parse("<template>{$markup}</template>")->template;

        foreach ($root->descendants() as $element) {
            if ($element->isElement()) {
                return $element;
            }
        }

        $this->fail("no element in {$markup}");
    }
}
