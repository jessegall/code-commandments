<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\OneRulePerFilterProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class OneRulePerFilterProphetTest extends TestCase
{
    private OneRulePerFilterProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new OneRulePerFilterProphet;
    }

    private function judge(string $chain): \JesseGall\CodeCommandments\Results\Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n\$result = \$collection->{$chain};\n");
    }

    public function test_flags_an_and_chain_in_filter(): void
    {
        $judgment = $this->judge('filter(static fn (User $u): bool => $u->active && $u->verified)');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('Split into one `->filter()` per rule', $judgment->warnings[0]->message);
    }

    public function test_counts_each_conjunct_in_a_long_and_chain(): void
    {
        $judgment = $this->judge('filter(fn ($x) => $x->a && $x->b && $x->c)');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('3 rules', $judgment->warnings[0]->message);
    }

    public function test_flags_the_double_negative_filter(): void
    {
        // The reported case: filter(!(A && B)) -> reject(A && B).
        $judgment = $this->judge('filter(static fn ($o): bool => ! ($o->schemaType !== null && $o->name === $o->schemaType))');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('->reject(', $judgment->warnings[0]->message);
    }

    public function test_flags_a_double_negative_reject_as_a_disguised_filter(): void
    {
        $judgment = $this->judge('reject(fn ($x) => ! ($x->a && $x->b))');

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('->filter(', $judgment->warnings[0]->message);
    }

    public function test_leaves_a_single_rule_filter(): void
    {
        $this->assertCount(0, $this->judge('filter(fn ($u) => $u->active)')->warnings);
        $this->assertCount(0, $this->judge('filter(fn ($o) => $o->total > 100)')->warnings);
        $this->assertCount(0, $this->judge('filter(fn ($x) => $x->ready())')->warnings);
    }

    public function test_leaves_a_single_negation(): void
    {
        // Not a compound — `! $x->hidden` is one rule. (The "also flag any negation"
        // option was deliberately not chosen.)
        $this->assertCount(0, $this->judge('filter(fn ($x) => ! $x->hidden)')->warnings);
    }

    public function test_leaves_a_top_level_or(): void
    {
        // An OR is one either/or rule; chained filters (an AND) can't express it.
        $this->assertCount(0, $this->judge('filter(fn ($j) => $j->urgent || $j->overdue)')->warnings);
    }

    public function test_leaves_an_and_chain_in_reject(): void
    {
        // reject(A && B) keeps NOT(A && B); reject chains as an OR, so it does NOT
        // split the AND. Only the double-negative reject is flagged.
        $this->assertCount(0, $this->judge('reject(fn ($x) => $x->a && $x->b)')->warnings);
    }

    public function test_ignores_a_multi_statement_closure(): void
    {
        $chain = 'filter(function ($x) { $ok = $x->a && $x->b; return $ok; })';

        $this->assertCount(0, $this->judge($chain)->warnings);
    }

    public function test_handles_a_single_return_closure(): void
    {
        $chain = 'filter(function ($u) { return $u->active && $u->verified; })';

        $this->assertCount(1, $this->judge($chain)->warnings);
    }
}
