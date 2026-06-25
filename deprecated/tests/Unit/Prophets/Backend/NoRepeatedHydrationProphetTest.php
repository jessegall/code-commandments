<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoRepeatedHydrationProphet;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoRepeatedHydrationProphetTest extends TestCase
{
    private NoRepeatedHydrationProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoRepeatedHydrationProphet();
    }

    private function judge(string $body): Judgment
    {
        $content = "<?php\nnamespace App;\nclass Service {\n{$body}\n}\n";

        return $this->prophet->judge('/x.php', $content);
    }

    // ────────────────────────────────────────────────────────────────
    // Real pattern mined from workflows: StepExtrasData::from(...->extras)
    // re-hydrated across three methods, including a nested base.
    // ────────────────────────────────────────────────────────────────

    public function test_flags_same_field_hydrated_three_times(): void
    {
        $judgment = $this->judge(<<<'PHP'
        public function a(array $steps, int $i): mixed {
            return StepExtrasData::from($steps[$i]->extras)->context;
        }
        public function b(StepEntry $step): mixed {
            return StepExtrasData::from($step->extras)->reason;
        }
        public function c(object $skipped): mixed {
            return StepExtrasData::from($skipped->getOrThrow()->extras)->exception;
        }
        PHP);

        $this->assertTrue($judgment->hasWarnings());
        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('StepExtrasData', $judgment->warnings[0]->message);
        $this->assertStringContainsString('3 times', $judgment->warnings[0]->message);
        $this->assertStringContainsString('extras', $judgment->warnings[0]->message);
    }

    public function test_two_occurrences_is_enough(): void
    {
        $judgment = $this->judge(<<<'PHP'
        public function a(object $x): mixed { return ExtrasData::from($x->extras); }
        public function b(object $y): mixed { return ExtrasData::from($y->extras); }
        PHP);

        $this->assertTrue($judgment->hasWarnings());
    }

    public function test_single_hydration_is_clean(): void
    {
        $judgment = $this->judge(<<<'PHP'
        public function a(object $x): mixed { return ExtrasData::from($x->extras); }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    // ────────────────────────────────────────────────────────────────
    // Real polymorphic pattern from workflows: many DIFFERENT *StaticInputs
    // types each hydrate $node->staticInputs — different targets, must NOT
    // group together.
    // ────────────────────────────────────────────────────────────────

    public function test_different_target_types_are_not_grouped(): void
    {
        $judgment = $this->judge(<<<'PHP'
        public function a(object $node): mixed { return DelayStaticInputs::from($node->staticInputs); }
        public function b(object $node): mixed { return ForEachStaticInputs::from($node->staticInputs); }
        public function c(object $node): mixed { return UniqueStaticInputs::from($node->staticInputs); }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_self_static_parent_from_is_ignored(): void
    {
        $judgment = $this->judge(<<<'PHP'
        public function a(object $node): mixed { return self::from($node->staticInputs); }
        public function b(object $node): mixed { return self::from($node->staticInputs); }
        public function c(object $node): mixed { return static::from($node->staticInputs); }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_variable_argument_is_not_flagged(): void
    {
        // ::from($row) in a loop is the "type the collection with DataCollectionOf"
        // case — a different rule. Only property-fetch args are this prophet's job.
        $judgment = $this->judge(<<<'PHP'
        public function a(array $rows): void { foreach ($rows as $row) { StepEntry::from($row); } }
        public function b(array $rows): void { foreach ($rows as $row) { StepEntry::from($row); } }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_generalises_to_repeated_enum_hydration(): void
    {
        $judgment = $this->judge(<<<'PHP'
        public function a(object $row): mixed { return Status::from($row->status); }
        public function b(object $other): mixed { return Status::from($other->status); }
        PHP);

        $this->assertTrue($judgment->hasWarnings());
        $this->assertStringContainsString('Status', $judgment->warnings[0]->message);
    }

    // ────────────────────────────────────────────────────────────────
    // Config
    // ────────────────────────────────────────────────────────────────

    public function test_severity_sin_blocks(): void
    {
        $this->prophet->configure(['severity' => 'sin']);

        $judgment = $this->judge(<<<'PHP'
        public function a(object $x): mixed { return ExtrasData::from($x->extras); }
        public function b(object $y): mixed { return ExtrasData::from($y->extras); }
        PHP);

        $this->assertTrue($judgment->isFallen());
        $this->assertFalse($judgment->hasWarnings());
    }

    public function test_min_occurrences_config(): void
    {
        $this->prophet->configure(['min_occurrences' => 3]);

        $two = $this->judge(<<<'PHP'
        public function a(object $x): mixed { return ExtrasData::from($x->extras); }
        public function b(object $y): mixed { return ExtrasData::from($y->extras); }
        PHP);
        $this->assertTrue($two->isRighteous(), 'Two occurrences should be clean at min_occurrences=3');

        $three = $this->judge(<<<'PHP'
        public function a(object $x): mixed { return ExtrasData::from($x->extras); }
        public function b(object $y): mixed { return ExtrasData::from($y->extras); }
        public function c(object $z): mixed { return ExtrasData::from($z->extras); }
        PHP);
        $this->assertTrue($three->hasWarnings(), 'Three occurrences should flag at min_occurrences=3');
    }

    public function test_advisory_is_complete(): void
    {
        $advisory = $this->prophet->advisory();

        $this->assertInstanceOf(Advisory::class, $advisory);
        $this->assertTrue($advisory->isComplete());
    }
}
