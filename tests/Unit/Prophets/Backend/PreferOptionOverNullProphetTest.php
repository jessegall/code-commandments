<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferOptionOverNullProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferOptionOverNullProphetTest extends TestCase
{
    private PreferOptionOverNullProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferOptionOverNullProphet();
    }

    // ────────────────────────────────────────────────────────────────
    // Core flagging — body decides between value and null
    // ────────────────────────────────────────────────────────────────

    public function test_flags_method_returning_value_or_null(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function findRef(array $edges, string $id): PortRef|null {
            foreach ($edges as $edge) {
                if ($edge->id === $id) {
                    return $edge->ref;
                }
            }

            return null;
        }
        PHP);

        $this->assertHasWarnings($judgment, 1);
        $this->assertStringContainsString('Service::findRef()', $judgment->warnings[0]->message);
        $this->assertStringContainsString('PortRef | null', $judgment->warnings[0]->message);
    }

    public function test_flags_without_declared_return_type(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function findRef(array $edges) {
            foreach ($edges as $edge) {
                if ($edge->active) {
                    return $edge;
                }
            }

            return null;
        }
        PHP);

        $this->assertHasWarnings($judgment, 1);
    }

    public function test_flags_nullable_shorthand_return_type(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function findRef(array $edges): ?PortRef {
            if ($edges === []) {
                return null;
            }

            return $edges[0];
        }
        PHP);

        $this->assertHasWarnings($judgment, 1);
        $this->assertStringContainsString('PortRef | null', $judgment->warnings[0]->message);
    }

    public function test_flags_ternary_with_null_branch(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function pick(bool $found, PortRef $ref): PortRef|null {
            return $found ? $ref : null;
        }
        PHP);

        $this->assertHasWarnings($judgment, 1);
    }

    public function test_flags_ternary_with_null_if_branch(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function pick(bool $missing, PortRef $ref): PortRef|null {
            return $missing ? null : $ref;
        }
        PHP);

        $this->assertHasWarnings($judgment, 1);
    }

    public function test_flags_multiple_null_and_value_returns(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function resolve(int $mode, PortRef $a, PortRef $b): PortRef|null {
            if ($mode === 0) {
                return null;
            }

            if ($mode === 1) {
                return $a;
            }

            if ($mode === 2) {
                return $b;
            }

            return null;
        }
        PHP);

        $this->assertHasWarnings($judgment, 1);
        $this->assertStringContainsString('2 `return null`', $judgment->warnings[0]->message);
        $this->assertStringContainsString('2 value returns', $judgment->warnings[0]->message);
    }

    public function test_flags_static_and_private_methods(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        private static function triggerSourceFor(object $node, object $graph): PortRef|null {
            foreach ($graph->edges as $edge) {
                if ($edge->to === $node->id) {
                    return $edge->from;
                }
            }

            return null;
        }
        PHP);

        $this->assertHasWarnings($judgment, 1);
        $this->assertStringContainsString('triggerSourceFor()', $judgment->warnings[0]->message);
    }

    public function test_flags_union_with_multiple_value_types(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function resolve(bool $flag): PortRef|string|null {
            if ($flag) {
                return 'literal';
            }

            return null;
        }
        PHP);

        $this->assertHasWarnings($judgment, 1);
        $this->assertStringContainsString('PortRef | string | null', $judgment->warnings[0]->message);
    }

    // ────────────────────────────────────────────────────────────────
    // Body over signature — the key requirement
    // ────────────────────────────────────────────────────────────────

    public function test_does_not_flag_nullable_signature_without_null_return_in_body(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function passthrough(): PortRef|null {
            return $this->repository->find();
        }
        PHP);

        $this->assertCleanFor($judgment);
    }

    public function test_does_not_flag_nullable_property_getter(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function label(): string|null {
            return $this->label;
        }
        PHP);

        $this->assertCleanFor($judgment);
    }

    public function test_does_not_flag_method_that_only_returns_null(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function nothing(): PortRef|null {
            return null;
        }
        PHP);

        $this->assertCleanFor($judgment);
    }

    public function test_does_not_flag_void_method_with_bare_returns(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function process(array $items): void {
            if ($items === []) {
                return;
            }

            $this->handle($items);
        }
        PHP);

        $this->assertCleanFor($judgment);
    }

    public function test_does_not_count_returns_inside_closures(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function names(array $rows): array {
            return array_map(function ($row) {
                if (! $row->valid) {
                    return null;
                }

                return $row->name;
            }, $rows);
        }
        PHP);

        $this->assertCleanFor($judgment);
    }

    public function test_does_not_count_returns_inside_anonymous_classes(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function makeResolver(): object {
            return new class {
                public function resolve(bool $x): string|null {
                    if ($x) {
                        return 'y';
                    }

                    return null;
                }
            };
        }
        PHP);

        // The anonymous class method itself IS flagged (it's a real method),
        // but the outer makeResolver() is not.
        $this->assertHasWarnings($judgment, 1);
        $this->assertStringContainsString('resolve()', $judgment->warnings[0]->message);
        $this->assertStringNotContainsString('makeResolver', $judgment->warnings[0]->message);
    }

    public function test_does_not_flag_ternary_without_null_branch(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function pick(bool $flag, PortRef $a, PortRef $b): PortRef {
            return $flag ? $a : $b;
        }
        PHP);

        $this->assertCleanFor($judgment);
    }

    // ────────────────────────────────────────────────────────────────
    // Exclusions
    // ────────────────────────────────────────────────────────────────

    public function test_does_not_flag_try_prefixed_methods_by_default(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public static function tryFrom(string $raw): self|null {
            if ($raw === '') {
                return null;
            }

            return new self();
        }
        PHP);

        $this->assertCleanFor($judgment);
    }

    public function test_does_not_flag_magic_methods_by_default(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function __get(string $name): mixed {
            if ($name === 'x') {
                return 1;
            }

            return null;
        }
        PHP);

        $this->assertCleanFor($judgment);
    }

    public function test_does_not_flag_override_methods(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        #[\Override]
        public function find(string $id): PortRef|null {
            if ($id === '') {
                return null;
            }

            return $this->refs[$id];
        }
        PHP);

        $this->assertCleanFor($judgment);
    }

    public function test_respects_custom_exclude_patterns(): void
    {
        $this->prophet->configure(['exclude_methods' => ['find*']]);

        $judgment = $this->judgeClass(<<<'PHP'
        public function findRef(string $id): PortRef|null {
            if ($id === '') {
                return null;
            }

            return $this->refs[$id];
        }
        PHP);

        $this->assertCleanFor($judgment);
    }

    public function test_custom_exclude_patterns_replace_defaults(): void
    {
        $this->prophet->configure(['exclude_methods' => ['find*']]);

        $judgment = $this->judgeClass(<<<'PHP'
        public static function tryParse(string $raw): self|null {
            if ($raw === '') {
                return null;
            }

            return new self();
        }
        PHP);

        $this->assertHasWarnings($judgment, 1);
    }

    // ────────────────────────────────────────────────────────────────
    // Suggestions — option_class and null_objects maps
    // ────────────────────────────────────────────────────────────────

    public function test_default_suggestion_mentions_introducing_option(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function findRef(string $id): PortRef|null {
            if ($id === '') {
                return null;
            }

            return $this->refs[$id];
        }
        PHP);

        $this->assertHasWarnings($judgment, 1);
        $this->assertStringContainsString('Option type', $judgment->warnings[0]->message);
        $this->assertStringContainsString('option_class', $judgment->warnings[0]->message);
    }

    public function test_configured_option_class_appears_in_suggestion(): void
    {
        $this->prophet->configure(['option_class' => 'App\\Support\\Option']);

        $judgment = $this->judgeClass(<<<'PHP'
        public function findRef(string $id): PortRef|null {
            if ($id === '') {
                return null;
            }

            return $this->refs[$id];
        }
        PHP);

        $this->assertHasWarnings($judgment, 1);
        $this->assertStringContainsString('App\Support\Option', $judgment->warnings[0]->message);
        $this->assertStringContainsString('Option::some($value)', $judgment->warnings[0]->message);
        $this->assertStringContainsString('Option::none()', $judgment->warnings[0]->message);
    }

    public function test_null_object_map_wins_over_option_for_matching_type(): void
    {
        $this->prophet->configure([
            'option_class' => 'App\\Support\\Option',
            'null_objects' => [
                'App\\Workflow\\PortRef' => 'App\\Workflow\\NullPortRef',
            ],
        ]);

        $content = <<<'PHP'
        <?php
        namespace App;
        use App\Workflow\PortRef;
        class Service {
            public function findRef(string $id): PortRef|null {
                if ($id === '') {
                    return null;
                }

                return $this->refs[$id];
            }
        }
        PHP;

        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertHasWarnings($judgment, 1);
        $this->assertStringContainsString('new NullPortRef', $judgment->warnings[0]->message);
        $this->assertStringNotContainsString('Option::some', $judgment->warnings[0]->message);
    }

    public function test_null_object_map_matches_by_short_name(): void
    {
        $this->prophet->configure([
            'null_objects' => [
                'App\\Other\\Namespace\\PortRef' => 'App\\Other\\Namespace\\NullPortRef',
            ],
        ]);

        $judgment = $this->judgeClass(<<<'PHP'
        public function findRef(string $id): PortRef|null {
            if ($id === '') {
                return null;
            }

            return $this->refs[$id];
        }
        PHP);

        $this->assertHasWarnings($judgment, 1);
        $this->assertStringContainsString('new NullPortRef', $judgment->warnings[0]->message);
    }

    public function test_null_object_map_does_not_match_other_types(): void
    {
        $this->prophet->configure([
            'null_objects' => [
                'App\\Workflow\\OtherThing' => 'App\\Workflow\\NullOtherThing',
            ],
        ]);

        $judgment = $this->judgeClass(<<<'PHP'
        public function findRef(string $id): PortRef|null {
            if ($id === '') {
                return null;
            }

            return $this->refs[$id];
        }
        PHP);

        $this->assertHasWarnings($judgment, 1);
        $this->assertStringNotContainsString('NullOtherThing', $judgment->warnings[0]->message);
    }

    // ────────────────────────────────────────────────────────────────
    // Severity config
    // ────────────────────────────────────────────────────────────────

    public function test_emits_warning_by_default(): void
    {
        $judgment = $this->judgeClass(<<<'PHP'
        public function findRef(string $id): PortRef|null {
            if ($id === '') {
                return null;
            }

            return $this->refs[$id];
        }
        PHP);

        $this->assertTrue($judgment->hasWarnings());
        $this->assertFalse($judgment->isFallen());
    }

    public function test_emits_sin_when_severity_configured(): void
    {
        $this->prophet->configure(['severity' => 'sin']);

        $judgment = $this->judgeClass(<<<'PHP'
        public function findRef(string $id): PortRef|null {
            if ($id === '') {
                return null;
            }

            return $this->refs[$id];
        }
        PHP);

        $this->assertTrue($judgment->isFallen());
        $this->assertCount(1, $judgment->sins);
        $this->assertStringContainsString('Option type', $judgment->sins[0]->suggestion);
    }

    // ────────────────────────────────────────────────────────────────
    // Robustness
    // ────────────────────────────────────────────────────────────────

    public function test_flags_in_trait(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        trait FindsRefs {
            public function findRef(string $id): object|null {
                if ($id === '') {
                    return null;
                }

                return $this->refs[$id];
            }
        }
        PHP;

        $this->assertHasWarnings($this->prophet->judge('/x.php', $content), 1);
    }

    public function test_flags_in_enum_method(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        enum Status: string {
            case Active = 'active';

            public function nextStatus(): self|null {
                if ($this === self::Active) {
                    return null;
                }

                return self::Active;
            }
        }
        PHP;

        $this->assertHasWarnings($this->prophet->judge('/x.php', $content), 1);
    }

    public function test_reports_method_start_line(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        class Service {
            private array $refs = [];

            public function findRef(string $id): object|null {
                if ($id === '') {
                    return null;
                }

                return $this->refs[$id];
            }
        }
        PHP;

        $judgment = $this->prophet->judge('/x.php', $content);
        $this->assertHasWarnings($judgment, 1);
        $this->assertSame(6, $judgment->warnings[0]->line);
    }

    public function test_handles_empty_file(): void
    {
        $this->assertCleanFor($this->prophet->judge('/x.php', '<?php'));
    }

    public function test_handles_invalid_php_gracefully(): void
    {
        $this->assertCleanFor($this->prophet->judge('/x.php', '<?php not valid <<<'));
    }

    public function test_handles_interface_methods_without_bodies(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;
        interface Finder {
            public function findRef(string $id): object|null;
        }
        PHP;

        $this->assertCleanFor($this->prophet->judge('/x.php', $content));
    }

    // ────────────────────────────────────────────────────────────────
    // Description sanity
    // ────────────────────────────────────────────────────────────────

    public function test_provides_helpful_descriptions(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertStringContainsString('Option', $this->prophet->description());
        $this->assertStringContainsString('Option::some', $this->prophet->detailedDescription());
        $this->assertStringContainsString('null_objects', $this->prophet->detailedDescription());
        $this->assertStringContainsString('@template', $this->prophet->detailedDescription());
    }

    // ────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────

    private function judgeClass(string $members): Judgment
    {
        $content = <<<PHP
        <?php
        namespace App;
        class Service {
            private array \$refs = [];

            private function handle(array \$items): void {}

            {$members}
        }
        PHP;

        return $this->prophet->judge('/x.php', $content);
    }

    private function assertHasWarnings(Judgment $judgment, ?int $expected = null): void
    {
        $this->assertTrue(
            $judgment->hasWarnings(),
            'Expected warnings. Sins: ' . json_encode(array_map(fn ($s) => $s->message, $judgment->sins))
        );

        if ($expected !== null) {
            $this->assertCount(
                $expected,
                $judgment->warnings,
                'Warnings: ' . json_encode(array_map(fn ($w) => $w->message, $judgment->warnings))
            );
        }
    }

    private function assertCleanFor(Judgment $judgment): void
    {
        $this->assertFalse(
            $judgment->isFallen(),
            'Sins: ' . json_encode(array_map(fn ($s) => $s->message, $judgment->sins))
        );
        $this->assertFalse(
            $judgment->hasWarnings(),
            'Warnings: ' . json_encode(array_map(fn ($w) => $w->message, $judgment->warnings))
        );
    }

    // ────────────────────────────────────────────────────────────────
    // Measure & suppress — caller count from the codebase index
    // ────────────────────────────────────────────────────────────────

    public function test_suppresses_when_resolved_callers_below_threshold(): void
    {
        // One internal caller, default threshold of 2 → measured as low-value,
        // stay silent.
        $judgment = $this->judgeWithIndex(<<<'PHP'
        <?php
        namespace App;
        class Service {
            public function findRef(array $edges): mixed {
                foreach ($edges as $e) { if ($e) { return $e; } }
                return null;
            }
            public function only(): void { $this->findRef([]); }
        }
        PHP);

        $this->assertCleanFor($judgment);
    }

    public function test_emits_when_callers_branch_on_absence_above_threshold(): void
    {
        // Three callers that each MANUALLY branch on the null — exactly where forcing
        // explicit Option handling pays for itself.
        $judgment = $this->judgeWithIndex(<<<'PHP'
        <?php
        namespace App;
        class Service {
            public function findRef(array $edges): mixed {
                foreach ($edges as $e) { if ($e) { return $e; } }
                return null;
            }
            public function a(): void { if ($this->findRef([]) === null) { return; } }
            public function b(): void { $x = $this->findRef([]); if ($x === null) { return; } }
            public function c(): void { $r = $this->findRef([]); if ($r !== null) { echo $r; } }
        }
        PHP);

        $this->assertHasWarnings($judgment, 1);
        $this->assertStringContainsString('3 call sites', $judgment->warnings[0]->message);
    }

    public function test_suppresses_when_callers_do_not_branch_on_absence(): void
    {
        // Three callers that DISCARD the result — they never branch on absence, so an
        // Option would buy nothing at any call site. The over-fire fix: it is no longer
        // enough to COUNT callers; they must actually juggle the null.
        $judgment = $this->judgeWithIndex(<<<'PHP'
        <?php
        namespace App;
        class Service {
            public function findRef(array $edges): mixed {
                foreach ($edges as $e) { if ($e) { return $e; } }
                return null;
            }
            public function a(): void { $this->findRef([]); }
            public function b(): void { $this->findRef([]); }
            public function c(): void { $this->findRef([]); }
        }
        PHP);

        $this->assertCleanFor($judgment);
    }

    public function test_high_threshold_suppresses_even_several_branching_callers(): void
    {
        $this->prophet->configure(['min_callers' => 10]);

        $judgment = $this->judgeWithIndex(<<<'PHP'
        <?php
        namespace App;
        class Service {
            public function findRef(array $edges): mixed {
                foreach ($edges as $e) { if ($e) { return $e; } }
                return null;
            }
            public function a(): void { if ($this->findRef([]) === null) { return; } }
            public function b(): void { $x = $this->findRef([]); if ($x === null) { return; } }
            public function c(): void { $r = $this->findRef([]); if ($r !== null) { echo $r; } }
        }
        PHP);

        $this->assertCleanFor($judgment);
    }

    public function test_no_index_is_not_suppressed(): void
    {
        // Without an index we cannot weigh the call sites — judge by the BODY alone
        // and emit. (Distinct from an index that positively resolves no branching
        // caller, which IS suppressed.)
        $prophet = new PreferOptionOverNullProphet;
        $code = "<?php\nnamespace App;\nclass Service { public function findRef(array \$edges): mixed { foreach (\$edges as \$e) { if (\$e) { return \$e; } } return null; } }";

        $this->assertTrue($prophet->judge('/x.php', $code)->hasWarnings());
    }

    public function test_index_resolving_no_branching_callers_is_suppressed(): void
    {
        // A boundary/framework method whose only resolved caller passes the value on —
        // never branches on absence — gains nothing from Option. Positive evidence, so
        // suppress (the fix for request accessors / Eloquent casts over-firing).
        $judgment = $this->judgeWithIndex(<<<'PHP'
        <?php
        namespace App;
        class Service {
            public function findRef(array $edges): mixed {
                foreach ($edges as $e) { if ($e) { return $e; } }
                return null;
            }
            public function pass(): mixed { return $this->findRef([]); }
        }
        PHP);

        $this->assertCleanFor($judgment);
    }

    public function test_leaves_a_framework_locked_signature(): void
    {
        // #94: handle() on a class with a vendor (Illuminate Command) ancestor is
        // framework-invoked — its return type isn't ours to change to Option.
        $this->prophet->configure(['min_callers' => 0]);

        $command = $this->judgeWithIndex("<?php\nnamespace App;\nclass MakeFooCommand extends \\Illuminate\\Console\\Command {\n public function handle(): bool | null { if (\$this->x()) { return false; } return null; }\n public function x() { return true; }\n}\n");
        $this->assertFalse($command->hasWarnings(), 'handle() on a vendor Command base is framework-locked.');

        $morph = $this->judgeWithIndex("<?php\nnamespace App;\nclass InputSocket extends \\Spatie\\LaravelData\\Data {\n public static function morph(array \$p): string | null { if (\$p) { return self::class; } return null; }\n}\n");
        $this->assertFalse($morph->hasWarnings(), 'spatie Data morph() is framework-locked.');
    }

    public function test_still_flags_a_project_owned_handle(): void
    {
        // A `handle()` whose class extends a PROJECT base (no vendor ancestor) is
        // ours to change — still flagged.
        $this->prophet->configure(['min_callers' => 0]);

        $judgment = $this->judgeWithIndex("<?php\nnamespace App;\nclass Base {}\nclass MyHandler extends Base {\n public function handle(): string | null { if (\$this->x()) { return 'a'; } return null; }\n public function x() { return true; }\n public function a(): void { if (\$this->handle() === null) { return; } }\n public function b(): void { \$r = \$this->handle(); if (\$r === null) { return; } }\n}\n");

        $this->assertTrue($judgment->hasWarnings());
    }

    private function judgeWithIndex(string $fileContent): Judgment
    {
        $dir = sys_get_temp_dir() . '/cc-prefer-' . uniqid();
        mkdir($dir);
        $path = $dir . '/Service.php';
        file_put_contents($path, $fileContent);

        $index = \JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex::build([$path]);
        $this->prophet->setCodebaseIndex($index);

        $judgment = $this->prophet->judge($path, $fileContent);

        @unlink($path);
        @rmdir($dir);

        return $judgment;
    }
}
