<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferCoalescingFactoryProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferCoalescingFactoryProphetTest extends TestCase
{
    private PreferCoalescingFactoryProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferCoalescingFactoryProphet();
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\nnamespace App;\nclass C {\n{$body}\n}\n");
    }

    public function test_flags_the_workflow_control_outputs_pattern(): void
    {
        // The exact shape reported: construct-or-null bag, then ?->/?? guards.
        $j = $this->judge(
            'public function f(array $value): void {'
            . ' foreach ($value as $entry) {'
            . '   $bag = is_array($entry) ? new Fluent($entry) : null;'
            . '   $name = $bag?->get("name") ?? (is_string($entry) ? $entry : null);'
            . '   $match = $bag?->get("match") ?? $name;'
            . ' } }'
        );

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString('Fluent::coalesce', $j->warnings[0]->message);
    }

    public function test_flags_reversed_branch_order(): void
    {
        $j = $this->judge('public function f($e): void { $bag = ! is_array($e) ? null : new Fluent($e); $x = $bag?->get("k"); }');

        $this->assertCount(1, $j->warnings);
    }

    public function test_flags_coalesce_guarded_use(): void
    {
        $j = $this->judge('public function f($e): mixed { $bag = is_array($e) ? new Fluent($e) : null; return $bag ?? "x"; }');

        $this->assertCount(1, $j->warnings);
    }

    public function test_leaves_construct_or_null_that_is_not_null_guarded(): void
    {
        // The null is branched on explicitly — a meaningful absent, not defended.
        $j = $this->judge('public function f($e): void { $bag = is_array($e) ? new Fluent($e) : null; if ($bag === null) { return; } $bag->get("k"); }');

        $this->assertTrue($j->isRighteous());
    }

    public function test_leaves_a_ternary_with_no_new(): void
    {
        $this->assertTrue($this->judge('public function f($e): void { $x = $e ? "a" : null; $y = $x?->foo(); }')->isRighteous());
    }
}
