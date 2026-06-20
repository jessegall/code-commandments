<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferCollectionPipelineProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferCollectionPipelineProphetTest extends TestCase
{
    private PreferCollectionPipelineProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferCollectionPipelineProphet;
    }

    public function test_flags_array_values_array_map(): void
    {
        // A value-predicate filter (not a type-guard) — the nest reads inside-out
        // and the Collection rewrite is safe, so it fires. (A type-narrowing filter
        // is exempt — see test_exempts_a_type_narrowing_filter_nest.)
        $j = $this->judge('return array_values(array_map(fn ($r) => $r, array_filter($rows, fn ($r) => $r->active)));');

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString('Collection chain', $j->warnings[0]->message);
    }

    public function test_exempts_a_type_narrowing_filter_nest(): void
    {
        // #146: array_filter with a type-guard narrows the element type, which
        // Collection::filter() does not — the rewrite would break the declared
        // list<T>. Covers first-class callables and compound `is_*($x) && …` guards.
        $this->assertTrue($this->judge('return array_values(array_filter($raw, is_array(...)));')->isRighteous());
        $this->assertTrue($this->judge('return array_values(array_filter($e, static fn ($x) => is_string($x) && $x !== ""));')->isRighteous());
        $this->assertTrue($this->judge('$o = array_map(fn ($x) => $x, array_filter($raw, fn ($x) => $x instanceof Foo));')->isRighteous());
    }

    public function test_flags_map_over_filter(): void
    {
        $j = $this->judge('$out = array_map(fn ($r) => f($r), array_filter($rows, fn ($r) => g($r)));');

        $this->assertCount(1, $j->warnings);
    }

    public function test_reports_composition_once_at_the_root(): void
    {
        // Three nested pipeline calls — one finding at the outermost only.
        $j = $this->judge('return array_values(array_map(fn ($r) => $r, array_filter($rows, fn ($r) => g($r))));');

        $this->assertCount(1, $j->warnings);
    }

    public function test_ignores_a_single_array_map(): void
    {
        $this->assertTrue($this->judge('return array_map(fn ($r) => f($r), $rows);')->isRighteous());
    }

    public function test_ignores_a_single_array_filter(): void
    {
        $this->assertTrue($this->judge('return array_filter($rows, fn ($r) => g($r));')->isRighteous());
    }

    public function test_ignores_array_map_with_non_pipeline_arg(): void
    {
        // array_map over a plain variable / a non-array_* call is fine.
        $this->assertTrue($this->judge('return array_map(fn ($r) => f($r), $this->rows());')->isRighteous());
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n" . $body);
    }
}
