<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferDataCollectionOfProphet;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferDataCollectionOfProphetTest extends TestCase
{
    private PreferDataCollectionOfProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferDataCollectionOfProphet();
    }

    private function judge(string $body): Judgment
    {
        $content = "<?php\nnamespace App;\nclass Service {\n{$body}\n}\n";

        return $this->prophet->judge('/x.php', $content);
    }

    // ────────────────────────────────────────────────────────────────
    // Real foreach-append shapes mined from workflows.
    // ────────────────────────────────────────────────────────────────

    public function test_flags_foreach_append_from(): void
    {
        // src/Http/Data/TestRunOutcomeData.php::hydrateSteps
        $judgment = $this->judge(<<<'PHP'
        public function steps(object $outcome): array {
            $hydrated = [];
            foreach ($outcome->steps as $row) {
                $hydrated[] = StepEntry::from($row);
            }
            return $hydrated;
        }
        PHP);

        $this->assertTrue($judgment->hasWarnings());
        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('StepEntry', $judgment->warnings[0]->message);
        $this->assertStringContainsString('foreach loop', $judgment->warnings[0]->message);
    }

    public function test_flags_foreach_append_from_with_derived_arg(): void
    {
        $judgment = $this->judge(<<<'PHP'
        public function fields(array $rows): array {
            $fields = [];
            foreach ($rows as $row) {
                $fields[] = SchemaFieldSpec::from($row);
            }
            return $fields;
        }
        PHP);

        $this->assertTrue($judgment->hasWarnings());
        $this->assertStringContainsString('SchemaFieldSpec', $judgment->warnings[0]->message);
    }

    // ────────────────────────────────────────────────────────────────
    // Real array_map shapes.
    // ────────────────────────────────────────────────────────────────

    public function test_flags_array_map_arrow_from(): void
    {
        // src/Http/Data/WorkflowEditorPage.php:594
        $judgment = $this->judge(<<<'PHP'
        public function fields(array $entries): array {
            return array_map(static fn (array $entry) => Field::from($entry), $entries);
        }
        PHP);

        $this->assertTrue($judgment->hasWarnings());
        $this->assertStringContainsString('Field', $judgment->warnings[0]->message);
        $this->assertStringContainsString('array_map', $judgment->warnings[0]->message);
    }

    public function test_flags_array_callable_form(): void
    {
        $judgment = $this->judge(<<<'PHP'
        public function fields(array $entries): array {
            return array_map([Field::class, 'from'], $entries);
        }
        PHP);

        $this->assertTrue($judgment->hasWarnings());
        $this->assertStringContainsString('Field', $judgment->warnings[0]->message);
    }

    public function test_flags_first_class_callable_form(): void
    {
        $judgment = $this->judge(<<<'PHP'
        public function fields(array $entries): array {
            return array_map(Field::from(...), $entries);
        }
        PHP);

        $this->assertTrue($judgment->hasWarnings());
    }

    // ────────────────────────────────────────────────────────────────
    // Must NOT flag — real counter-examples.
    // ────────────────────────────────────────────────────────────────

    public function test_ignores_custom_factory_in_array_map(): void
    {
        // src/Http/Data/NodeDescriptorData.php — fromInputPort is genuine mapping.
        $judgment = $this->judge(<<<'PHP'
        public function ports(object $d): array {
            return array_map(static fn ($p) => NodePortData::fromInputPort($p), $d->inputs);
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_ignores_loop_that_does_real_work(): void
    {
        $judgment = $this->judge(<<<'PHP'
        public function steps(array $rows): array {
            $out = [];
            foreach ($rows as $row) {
                if ($row->skip) { continue; }
                $out[$row->id] = $row->value * 2;
            }
            return $out;
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_ignores_append_not_referencing_loop_var(): void
    {
        $judgment = $this->judge(<<<'PHP'
        public function steps(array $rows, object $external): array {
            $out = [];
            foreach ($rows as $row) {
                $out[] = StepEntry::from($external->fixed);
            }
            return $out;
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_ignores_self_from(): void
    {
        $judgment = $this->judge(<<<'PHP'
        public function steps(array $rows): array {
            $out = [];
            foreach ($rows as $row) {
                $out[] = self::from($row);
            }
            return $out;
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    // ────────────────────────────────────────────────────────────────
    // Config
    // ────────────────────────────────────────────────────────────────

    public function test_severity_sin_blocks(): void
    {
        $this->prophet->configure(['severity' => 'sin']);

        $judgment = $this->judge(<<<'PHP'
        public function steps(array $rows): array {
            $out = [];
            foreach ($rows as $row) { $out[] = StepEntry::from($row); }
            return $out;
        }
        PHP);

        $this->assertTrue($judgment->isFallen());
        $this->assertFalse($judgment->hasWarnings());
    }

    public function test_does_not_flag_a_backed_enum_from_in_a_loop(): void
    {
        // #217: a backed enum's ::from() is the enum's value-of constructor, not
        // Spatie Data collection hydration — and a backed enum has no ::collect(),
        // so the suggested fix is impossible. `JesseGall\PhpTypes\T` is a real,
        // loadable string-backed enum, so the prophet can tell it is not Data.
        $content = <<<'PHP'
        <?php
        namespace App;
        use JesseGall\PhpTypes\T;
        class Service {
            public function kinds(array $map): array {
                $out = [];
                foreach (array_keys($map) as $v) {
                    $out[] = T::from($v);
                }
                return $out;
            }
        }
        PHP;

        $judgment = $this->prophet->judge('/x.php', $content);

        $this->assertFalse($judgment->hasWarnings(), 'a backed-enum ::from() must not be flagged as Data hydration');
        $this->assertFalse($judgment->isFallen());
    }

    public function test_advisory_is_complete(): void
    {
        $advisory = $this->prophet->advisory();

        $this->assertInstanceOf(Advisory::class, $advisory);
        $this->assertTrue($advisory->isComplete());
    }
}
