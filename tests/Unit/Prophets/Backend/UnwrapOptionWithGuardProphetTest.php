<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\UnwrapOptionWithGuardProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class UnwrapOptionWithGuardProphetTest extends TestCase
{
    private UnwrapOptionWithGuardProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new UnwrapOptionWithGuardProphet();
    }

    private function judge(string $body): \JesseGall\CodeCommandments\Results\Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\nnamespace App;\nclass C {\n{$body}\n}\n");
    }

    public function test_flags_guard_then_unwrap(): void
    {
        $j = $this->judge('public function a($node): mixed { if ($node->isEmpty()) { return 1; } $d = $node->getOrThrow(); return $d; }');
        $this->assertTrue($j->hasWarnings());
        $this->assertStringContainsString('asks-then-unwraps', $j->warnings[0]->message);
    }

    public function test_flags_negated_has_value_with_continue(): void
    {
        $j = $this->judge('public function a(array $opts): void { foreach ($opts as $o) { if (! $o->hasValue()) { continue; } $v = $o->getOrThrow(); echo $v; } }');
        $this->assertTrue($j->hasWarnings());
    }

    public function test_does_not_flag_when_guard_does_work(): void
    {
        $j = $this->judge('public function a($opt): mixed { if ($opt->isEmpty()) { $this->log("x"); return 2; } $x = $opt->getOrThrow(); return $x; }');
        $this->assertFalse($j->hasWarnings());
    }

    public function test_does_not_flag_unwrap_of_a_different_variable(): void
    {
        $j = $this->judge('public function a($opt, $other): mixed { if ($opt->isEmpty()) { return 1; } $x = $other->getOrThrow(); return $x; }');
        $this->assertFalse($j->hasWarnings());
    }

    /**
     * #165: a divergent early-return guard whose empty branch returns a COMPUTED
     * alternative (an instance method call) — the two absence outcomes differ and the
     * present branch can itself be none() with a distinct meaning, which getOr()/
     * orElse() cannot express. Must NOT fire.
     */
    public function test_does_not_flag_a_guard_returning_a_computed_alternative(): void
    {
        $j = $this->judge('public function controlSourceFor($node, $dataSource, $graph) { if ($dataSource->isEmpty()) { return $this->triggerSourceFor($node, $graph); } $source = $dataSource->getOrThrow(); return $graph->nodeById($source->nodeId); }');
        $this->assertFalse($j->hasWarnings());
    }

    public function test_does_not_flag_a_guard_returning_a_nullsafe_computed_alternative(): void
    {
        $j = $this->judge('public function a($opt) { if ($opt->isEmpty()) { return $this?->fallback(); } $x = $opt->getOrThrow(); return $x; }');
        $this->assertFalse($j->hasWarnings());
    }

    public function test_still_flags_a_static_sentinel_default_like_option_none(): void
    {
        $j = $this->judge('public function a($opt) { if ($opt->isEmpty()) { return Option::none(); } $x = $opt->getOrThrow(); return $x; }');
        $this->assertTrue($j->hasWarnings(), 'a static sentinel default is a trivial default, still flaggable');
    }

    public function test_does_not_flag_a_two_way_branch(): void
    {
        $j = $this->judge('public function a($opt): mixed { if ($opt->isEmpty()) { return 1; } else { return 2; } }');
        $this->assertFalse($j->hasWarnings());
    }

    public function test_marks_the_safe_triple_autofixable(): void
    {
        $j = $this->judge('public function a($node): mixed { if ($node->isEmpty()) { return 0; } $d = $node->getOrThrow(); return $this->wrap($d); }');
        $this->assertTrue($j->warnings[0]->autoFixable);
    }

    public function test_does_not_mark_a_continue_guard_autofixable(): void
    {
        $j = $this->judge('public function a(array $os): void { foreach ($os as $o) { if ($o->isEmpty()) { continue; } $v = $o->getOrThrow(); echo $v; } }');
        $this->assertNotEmpty($j->warnings);
        $this->assertFalse($j->warnings[0]->autoFixable);
    }

    public function test_repent_rewrites_the_safe_triple_to_transform_get_or(): void
    {
        $src = "<?php\nnamespace App;\nclass C {\n public function a(\$node): mixed { if (\$node->isEmpty()) { return ControlSockets::OUT; } \$d = \$node->getOrThrow(); return \$this->wrap(\$d); }\n}\n";

        $result = $this->prophet->repent('/x.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('return $node->transform(fn ($d) => $this->wrap($d))->getOr(ControlSockets::OUT);', $result->newContent);
        $this->assertStringNotContainsString('getOrThrow', $result->newContent);
    }

    public function test_repent_leaves_a_continue_guard_untouched(): void
    {
        $src = "<?php\nnamespace App;\nclass C {\n public function a(array \$os): void { foreach (\$os as \$o) { if (\$o->isEmpty()) { continue; } \$v = \$o->getOrThrow(); echo \$v; } }\n}\n";

        $result = $this->prophet->repent('/x.php', $src);

        $this->assertFalse($result->absolved, 'A continue-guard is not the safe shape — leave it for a human.');
    }
}
