<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferOptionFactoryProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use PHPUnit\Framework\TestCase;

class PreferOptionFactoryProphetTest extends TestCase
{
    private PreferOptionFactoryProphet $prophet;

    protected function setUp(): void
    {
        $this->prophet = new PreferOptionFactoryProphet();
    }

    public function test_flags_some_none_ternary_and_suggests_someWhen(): void
    {
        // The reported case.
        $judgment = $this->judge(<<<'PHP'
public function descriptorFor(string $nodeId): Option
{
    return $this->instance->has($nodeId)
        ? Option::some($this->instance->node($nodeId)->descriptor)
        : Option::none();
}
PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('option-some-none-branch', $judgment->warnings[0]->symbol);
        $this->assertStringContainsString('Option::someWhen($this->instance->has($nodeId), fn () => $this->instance->node($nodeId)->descriptor)', $judgment->warnings[0]->message);
    }

    public function test_flags_make_wrapping_a_null_ternary_and_suggests_someWhen(): void
    {
        // The "fix" that just moved the ternary inside make().
        $judgment = $this->judge(<<<'PHP'
public function descriptorFor(string $nodeId): Option
{
    return Option::make($this->instance->has($nodeId) ? $this->instance->node($nodeId)->descriptor : null);
}
PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertSame('option-make-ternary', $judgment->warnings[0]->symbol);
        $this->assertStringContainsString('Option::someWhen($this->instance->has($nodeId), fn () => $this->instance->node($nodeId)->descriptor)', $judgment->warnings[0]->message);
    }

    public function test_make_with_null_on_the_true_branch_suggests_someWhenNot(): void
    {
        $judgment = $this->judge('public function f(): Option { return Option::make($hidden ? null : $value); }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('Option::someWhenNot($hidden, fn () => $value)', $judgment->warnings[0]->message);
    }

    public function test_null_check_ternary_suggests_make(): void
    {
        $judgment = $this->judge('public function f($x): Option { return $x !== null ? Option::some($x) : Option::none(); }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('Option::make($x)', $judgment->warnings[0]->message);
    }

    public function test_isset_ternary_suggests_find(): void
    {
        $judgment = $this->judge('public function f(string $k): Option { return isset($this->items[$k]) ? Option::some($this->items[$k]) : Option::none(); }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('Option::find($this->items, $k)', $judgment->warnings[0]->message);
    }

    public function test_if_else_form_is_flagged(): void
    {
        $judgment = $this->judge(<<<'PHP'
public function f(string $id): Option
{
    if ($this->reg->has($id)) {
        return Option::some($this->reg->get($id));
    } else {
        return Option::none();
    }
}
PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('Option::someWhen($this->reg->has($id), fn () => $this->reg->get($id))', $judgment->warnings[0]->message);
    }

    public function test_some_on_the_false_branch_suggests_someWhenNot(): void
    {
        $judgment = $this->judge('public function f($cond, $v): Option { return $cond ? Option::none() : Option::some($v); }');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('Option::someWhenNot($cond, fn () => $v)', $judgment->warnings[0]->message);
    }

    public function test_does_not_flag_a_plain_make_or_when(): void
    {
        $this->assertTrue($this->judge('public function f($x): Option { return Option::make($x); }')->isRighteous());
        $this->assertTrue($this->judge('public function f($c, $v): Option { return Option::when($c, fn () => $v); }')->isRighteous());
        $this->assertTrue($this->judge('public function f($c, $v): Option { return $c ? Option::some($v) : Option::some($v); }')->isRighteous());
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n\nnamespace App;\n\nclass C {\n{$body}\n}\n");
    }
}
