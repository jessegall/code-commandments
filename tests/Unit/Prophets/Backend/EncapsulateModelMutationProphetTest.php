<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\EncapsulateModelMutationProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class EncapsulateModelMutationProphetTest extends TestCase
{
    private EncapsulateModelMutationProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new EncapsulateModelMutationProphet;
    }

    public function test_flags_a_self_referential_counter_then_save(): void
    {
        // The reported case: $workflow->edit_seq = $workflow->edit_seq + 1; save();
        $judgment = $this->judge(
            'class D { public function dispatch($workflow): void {'
            . ' $workflow->edit_seq = $workflow->edit_seq + 1; $workflow->save(); } }'
        );

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('increment', $judgment->warnings[0]->message);
    }

    public function test_flags_a_compound_assignment_counter(): void
    {
        $judgment = $this->judge(
            'class D { public function f($m): void { $m->count += 1; $m->save(); } }'
        );

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('increment', $judgment->warnings[0]->message);
    }

    public function test_flags_an_enum_state_transition(): void
    {
        $judgment = $this->judge(
            'class D { public function ship($order): void { $order->status = OrderStatus::Shipped; $order->save(); } }'
        );

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('transition', $judgment->warnings[0]->message);
    }

    public function test_flags_multiple_attribute_writes_then_save(): void
    {
        $judgment = $this->judge(
            'class D { public function verify($user): void {'
            . ' $user->verified_at = now(); $user->verification_token = null; $user->save(); } }'
        );

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('Several attributes', $judgment->warnings[0]->message);
    }

    public function test_does_not_flag_writes_through_this(): void
    {
        // Already inside the record — this is exactly where the behaviour belongs.
        $judgment = $this->judge(
            'class Workflow { public function incrementSequenceNumber(): void {'
            . ' $this->edit_seq = $this->edit_seq + 1; $this->save(); } }'
        );

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_save_without_a_preceding_attribute_write(): void
    {
        $judgment = $this->judge(
            'class D { public function f($m): void { $this->prepare(); $m->save(); } }'
        );

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_when_an_unrelated_statement_separates_the_write_and_save(): void
    {
        // The run of attribute writes must DIRECTLY precede the save().
        $judgment = $this->judge(
            'class D { public function f($m): void { $m->status = X::A; $this->log(); $m->save(); } }'
        );

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_save_on_a_different_variable(): void
    {
        $judgment = $this->judge(
            'class D { public function f($a, $b): void { $a->status = X::A; $b->save(); } }'
        );

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_class_string_assignment(): void
    {
        // `::class` is a class-string, not a closed-set state — but a plain
        // attribute write still anchors the smell; assert it is flagged as the
        // generic (non-enum) form rather than mislabelled a transition.
        $judgment = $this->judge(
            'class D { public function f($m): void { $m->type = Foo::class; $m->save(); } }'
        );

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringNotContainsString('transition', $judgment->warnings[0]->message);
    }

    public function test_persist_method_is_configurable(): void
    {
        $prophet = (new EncapsulateModelMutationProphet)->configure(['persist_methods' => ['store']]);

        $flagged = $prophet->judge('/x.php', "<?php\nclass D { public function f(\$m): void { \$m->status = X::A; \$m->store(); } }");
        $this->assertCount(1, $flagged->warnings);

        // The default `save` is replaced, so a plain save() no longer fires.
        $quiet = $prophet->judge('/x.php', "<?php\nclass D { public function f(\$m): void { \$m->status = X::A; \$m->save(); } }");
        $this->assertTrue($quiet->isRighteous());
    }

    public function test_flags_the_real_fixture(): void
    {
        $path = __DIR__ . '/../../../Fixtures/Backend/Sinful/EncapsulateModelMutation/EditorActionDispatcher.php';
        $judgment = $this->prophet->judge($path, (string) file_get_contents($path));

        // All three call sites in the fixture (counter, enum, multi-field).
        $this->assertCount(3, $judgment->warnings);
    }

    public function test_is_advisory_not_a_sin(): void
    {
        $judgment = $this->judge(
            'class D { public function f($m): void { $m->status = X::A; $m->save(); } }'
        );

        $this->assertSame([], $judgment->sins);
        $this->assertNotEmpty($judgment->warnings);
        $this->assertNotTrue($judgment->warnings[0]->autoFixable);
    }

    public function test_flags_every_case_in_the_corpus(): void
    {
        // 18 methods, each carrying exactly one mutate-then-save smell across every
        // assignment shape and control-flow position — a 1:1 match guards against
        // both missed cases and new false positives.
        $path = __DIR__ . '/../../../Fixtures/Backend/Sinful/EncapsulateModelMutation/ManyFlaggedCases.php';
        $judgment = $this->prophet->judge($path, (string) file_get_contents($path));

        $this->assertCount(18, $judgment->warnings);
    }

    public function test_stays_silent_on_the_clean_corpus(): void
    {
        $path = __DIR__ . '/../../../Fixtures/Backend/Clean/EncapsulateModelMutation/CleanCases.php';
        $judgment = $this->prophet->judge($path, (string) file_get_contents($path));

        $this->assertTrue($judgment->isRighteous(), 'clean corpus must produce no warnings: '
            . implode(' | ', array_map(static fn ($w) => $w->message, $judgment->warnings)));
    }

    public function test_flags_an_increment_operator_counter(): void
    {
        $judgment = $this->judge(
            'class D { public function f($m): void { $m->version++; $m->save(); } }'
        );

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('increment', $judgment->warnings[0]->message);
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertNotNull($this->prophet->advisory());
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n" . $body);
    }
}
