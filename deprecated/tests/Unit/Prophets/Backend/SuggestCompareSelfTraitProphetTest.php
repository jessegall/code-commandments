<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\SuggestCompareSelfTraitProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class SuggestCompareSelfTraitProphetTest extends TestCase
{
    private SuggestCompareSelfTraitProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prophet = new SuggestCompareSelfTraitProphet;
        $this->prophet->configure([
            'trait' => 'App\\Support\\Enums\\CompareSelf',
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    // Primary rewrite (enum uses the trait)
    // ────────────────────────────────────────────────────────────────

    public function test_flags_or_chain_when_enum_uses_trait(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum NodeKind {
            use CompareSelf;
            case Input;
            case Output;
            case Trigger;
        }

        class Router {
            public function route(NodeKind $kind): bool {
                if ($kind === NodeKind::Input || $kind === NodeKind::Output) {
                    return true;
                }
                return false;
            }
        }
        PHP);

        $this->assertCount(0, $judgment->sins);
        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('$kind->equalsAny(', $judgment->warnings[0]->message);
        $this->assertStringNotContainsString('[ADOPT]', $judgment->warnings[0]->message);
        $this->assertStringContainsString('NodeKind::Input', $judgment->warnings[0]->message);
        $this->assertStringContainsString('NodeKind::Output', $judgment->warnings[0]->message);
        $this->assertTrue($judgment->warnings[0]->autoFixable);
    }

    public function test_flags_and_chain_with_not_identical_when_enum_uses_trait(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum Status {
            use CompareSelf;
            case Pending;
            case Done;
            case Failed;
        }

        class Worker {
            public function isInFlight(Status $status): bool {
                return $status !== Status::Done && $status !== Status::Failed;
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('$status->notEqualsAny(', $judgment->warnings[0]->message);
        $this->assertStringNotContainsString('[ADOPT]', $judgment->warnings[0]->message);
    }

    public function test_flags_three_chain(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum Foo {
            use CompareSelf;
            case A;
            case B;
            case C;
        }

        class Sieve {
            public function pick(Foo $kind): bool {
                return $kind === Foo::A || $kind === Foo::B || $kind === Foo::C;
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('$kind->equalsAny(Foo::A, Foo::B, Foo::C)', $judgment->warnings[0]->message);
    }

    public function test_handles_property_fetch_lhs(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum NodeKind {
            use CompareSelf;
            case Input;
            case Output;
            case Control;
        }

        class Registry {
            public function withImplicit($descriptor): bool {
                return $descriptor->kind === NodeKind::Input
                    || $descriptor->kind === NodeKind::Output
                    || $descriptor->kind === NodeKind::Control;
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('NodeKind::equalsAny($descriptor->kind,', $judgment->warnings[0]->message);
    }

    // ────────────────────────────────────────────────────────────────
    // Adoption hint (enum exists but no trait)
    // ────────────────────────────────────────────────────────────────

    public function test_emits_adoption_hint_when_enum_does_not_use_trait(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        enum LegacyKind {
            case X;
            case Y;
        }

        class Gate {
            public function check(LegacyKind $legacy): bool {
                return $legacy === LegacyKind::X || $legacy === LegacyKind::Y;
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('[ADOPT]', $judgment->warnings[0]->message);
        $this->assertStringContainsString('LegacyKind', $judgment->warnings[0]->message);
        $this->assertStringContainsString('CompareSelf', $judgment->warnings[0]->message);
    }

    public function test_ignores_constant_comparison_on_non_enum_class(): void
    {
        // A value/node class with string constants is NOT an enum — `$x ===
        // PackNode::KEY` is a plain constant comparison, not enum equality.
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        final class PackNode {
            public const string KEY = 'pack';
        }

        class Registry {
            public function expand(object $descriptor): bool {
                return $descriptor->key === PackNode::KEY;
            }
        }
        PHP);

        $this->assertCount(0, $judgment->warnings);
    }

    public function test_ignores_self_constant_comparison_in_value_class(): void
    {
        // `self::MIXED` inside a non-enum class — a string-constant comparison.
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        final readonly class WireType {
            public const string MIXED = 'mixed';

            public function isMixed(mixed $token): bool {
                return $token === null || $token === self::MIXED;
            }
        }
        PHP);

        $this->assertCount(0, $judgment->warnings);
    }

    public function test_deduplicates_adoption_hint_per_enum_per_file(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        enum LegacyKind {
            case X;
            case Y;
            case Z;
        }

        class Gate {
            public function checkOne(LegacyKind $legacy): bool {
                return $legacy === LegacyKind::X || $legacy === LegacyKind::Y;
            }

            public function checkTwo(LegacyKind $legacy): bool {
                return $legacy === LegacyKind::Y || $legacy === LegacyKind::Z;
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('[ADOPT]', $judgment->warnings[0]->message);
    }

    // ────────────────────────────────────────────────────────────────
    // Negative cases
    // ────────────────────────────────────────────────────────────────

    public function test_flags_single_identical_as_equals(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum NodeKind {
            use CompareSelf;
            case Input;
        }

        class Router {
            public function route(NodeKind $kind): bool {
                return $kind === NodeKind::Input;
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('NodeKind::Input->equals($kind)', $judgment->warnings[0]->message);
        $this->assertStringNotContainsString('equalsAny', $judgment->warnings[0]->message);
        $this->assertTrue($judgment->warnings[0]->autoFixable);
    }

    public function test_flags_single_not_identical_as_not_equals(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum NodeKind {
            use CompareSelf;
            case Input;
        }

        class Router {
            public function route(NodeKind $kind): bool {
                return $kind !== NodeKind::Input;
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('NodeKind::Input->notEquals($kind)', $judgment->warnings[0]->message);
        $this->assertStringNotContainsString('notEqualsAny', $judgment->warnings[0]->message);
    }

    public function test_mixed_lhs_chain_yields_two_separate_singles(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum Foo {
            use CompareSelf;
            case A;
            case B;
        }

        class Picker {
            public function pick($a, $b): bool {
                return $a === Foo::A || $b === Foo::B;
            }
        }
        PHP);

        // The chain analysis fails (different LHS), so neither atom is consumed
        // and each surfaces as its own single `equals` finding.
        $this->assertCount(2, $judgment->warnings);
        $messages = implode("\n", array_map(fn ($w) => $w->message, $judgment->warnings));
        $this->assertStringContainsString('Foo::A->equals($a)', $messages);
        $this->assertStringContainsString('Foo::B->equals($b)', $messages);
    }

    public function test_real_chain_does_not_double_count_with_singles(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum Foo {
            use CompareSelf;
            case A;
            case B;
        }

        class Sieve {
            public function pick(Foo $kind): bool {
                return $kind === Foo::A || $kind === Foo::B;
            }
        }
        PHP);

        // One chain warning only — the two atoms are consumed, not re-emitted.
        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('$kind->equalsAny(Foo::A, Foo::B)', $judgment->warnings[0]->message);
    }

    public function test_mixed_enum_chain_yields_singles_per_enum(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum Foo {
            use CompareSelf;
            case A;
        }

        enum Bar {
            use CompareSelf;
            case Y;
        }

        class Tester {
            public function check($x): bool {
                return $x === Foo::A || $x === Bar::Y;
            }
        }
        PHP);

        // No valid same-enum chain, so each side is a standalone single.
        $this->assertCount(2, $judgment->warnings);
        $messages = implode("\n", array_map(fn ($w) => $w->message, $judgment->warnings));
        $this->assertStringContainsString('Foo::A->equals($x)', $messages);
        $this->assertStringContainsString('Bar::Y->equals($x)', $messages);
    }

    public function test_ignores_a_narrowing_guard_before_an_exhaustive_match(): void
    {
        // Issue #12: `if ($x === Case) continue;` is a load-bearing narrowing
        // guard — PHPStan narrows through ===, not equals(). Leave it.
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum Source {
            use CompareSelf;
            case A;
            case B;
        }

        class Pipe {
            public function go(array $ports): void {
                foreach ($ports as $port) {
                    if ($port->source === Source::A) {
                        continue;
                    }
                }
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_ignores_a_narrowing_guard_with_return(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum Source {
            use CompareSelf;
            case A;
        }

        class Pipe {
            public function go($port): mixed {
                if ($port->source === Source::A) {
                    return null;
                }
                return $port;
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_still_flags_a_non_bail_if_body(): void
    {
        // The if body is NOT a bail (it does work) — no narrowing depends on it,
        // so the comparison is still flagged.
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum Source {
            use CompareSelf;
            case A;
        }

        class Pipe {
            public function go($port): void {
                if ($port->source === Source::A) {
                    $this->log('a');
                }
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_ignores_match_expression(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum NodeKind {
            use CompareSelf;
            case Input;
            case Output;
            case Other;
        }

        class Labeler {
            public function label(NodeKind $kind): string {
                return match ($kind) {
                    NodeKind::Input, NodeKind::Output => 'IO',
                    default => 'other',
                };
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_or_chain_with_non_identical_atom_yields_single(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum Foo {
            use CompareSelf;
            case A;
            case B;
        }

        class Mixer {
            public function check(Foo $kind, bool $flag): bool {
                return $kind === Foo::A || $flag;
            }
        }
        PHP);

        // The chain isn't a pure enum-equality chain, so only the lone
        // `$kind === Foo::A` atom surfaces, as a single equals.
        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('Foo::A->equals($kind)', $judgment->warnings[0]->message);
    }

    public function test_ignores_chain_inside_to_array(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum Foo {
            use CompareSelf;
            case A;
            case B;
        }

        class Bag {
            private Foo $kind;
            public function toArray(): array {
                $isAOrB = $this->kind === Foo::A || $this->kind === Foo::B;
                return ['flag' => $isAOrB];
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_ignores_chain_inside_resource_class(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        use App\Support\Enums\CompareSelf;
        use Illuminate\Http\Resources\Json\JsonResource;

        enum Foo {
            use CompareSelf;
            case A;
            case B;
        }

        class WidgetResource extends JsonResource {
            public function render(): bool {
                return $this->resource->kind === Foo::A || $this->resource->kind === Foo::B;
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    // ────────────────────────────────────────────────────────────────
    // Configuration
    // ────────────────────────────────────────────────────────────────

    public function test_min_chain_three_skips_two_chain(): void
    {
        $this->prophet->configure(['min_chain' => 3]);

        $judgment = $this->judge(<<<'PHP'
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum Foo {
            use CompareSelf;
            case A;
            case B;
        }

        class Sieve {
            public function pick(Foo $kind): bool {
                return $kind === Foo::A || $kind === Foo::B;
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_exclude_enums_suppresses_warning(): void
    {
        $this->prophet->configure(['exclude_enums' => ['App\\NodeKind']]);

        $judgment = $this->judge(<<<'PHP'
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum NodeKind {
            use CompareSelf;
            case Input;
            case Output;
        }

        class Router {
            public function route(NodeKind $kind): bool {
                return $kind === NodeKind::Input || $kind === NodeKind::Output;
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_custom_method_names_appear_in_suggestion(): void
    {
        $this->prophet->configure([
            'equals_any_method' => 'isAny',
            'not_equals_any_method' => 'isNone',
        ]);

        $judgment = $this->judge(<<<'PHP'
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum Foo {
            use CompareSelf;
            case A;
            case B;
        }

        class Sieve {
            public function pick(Foo $kind): bool {
                return $kind === Foo::A || $kind === Foo::B;
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('$kind->isAny(', $judgment->warnings[0]->message);
        $this->assertStringNotContainsString('equalsAny', $judgment->warnings[0]->message);
    }

    public function test_custom_trait_fqcn_resolves_via_use_alias(): void
    {
        $this->prophet->configure(['trait' => 'Acme\\Enums\\CompareSelf']);

        $judgment = $this->judge(<<<'PHP'
        namespace App\Domain;

        use Acme\Enums\CompareSelf;

        enum Status {
            use CompareSelf;
            case Open;
            case Closed;
        }

        class S {
            public function pick(Status $status): bool {
                return $status === Status::Open || $status === Status::Closed;
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringNotContainsString('[ADOPT]', $judgment->warnings[0]->message);
    }

    // ────────────────────────────────────────────────────────────────
    // Auto-fix (repent)
    // ────────────────────────────────────────────────────────────────

    public function test_repent_rewrites_all_four_shapes(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum Status {
            use CompareSelf;
            case A;
            case B;
        }

        class C {
            public function a($x): bool { return $x === Status::A; }
            public function b($x): bool { return $x !== Status::A; }
            public function c($x): bool { return $x === Status::A || $x === Status::B; }
            public function d($x): bool { return $x !== Status::A && $x !== Status::B; }
        }
        PHP;

        $result = $this->prophet->repent('/x.php', $content);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('return Status::A->equals($x);', $result->newContent);
        $this->assertStringContainsString('return Status::A->notEquals($x);', $result->newContent);
        $this->assertStringContainsString('return Status::equalsAny($x, Status::A, Status::B);', $result->newContent);
        $this->assertStringContainsString('return Status::notEqualsAny($x, Status::A, Status::B);', $result->newContent);
    }

    public function test_repent_preserves_written_class_reference(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;

        enum Status {
            use \App\Support\Enums\CompareSelf;
            case A;
        }

        class C {
            public function a($x): bool { return $x === \App\Status::A; }
        }
        PHP;

        $result = $this->prophet->repent('/x.php', $content);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('return \App\Status::A->equals($x);', $result->newContent);
    }

    public function test_repent_leaves_adoption_hint_findings_untouched(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;

        enum LegacyKind {
            case X;
            case Y;
        }

        class Gate {
            public function check(LegacyKind $legacy): bool {
                return $legacy === LegacyKind::X || $legacy === LegacyKind::Y;
            }
        }
        PHP;

        $result = $this->prophet->repent('/x.php', $content);

        // Enum does not use the trait — rewriting would call a missing
        // __callStatic, so repent makes no change.
        $this->assertFalse($result->absolved);
        $this->assertNull($result->newContent);
    }

    // ────────────────────────────────────────────────────────────────
    // in_array membership tests
    // ────────────────────────────────────────────────────────────────

    public function test_flags_in_array_over_enum_cases_as_one_of(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum Status {
            use CompareSelf;
            case A;
            case B;
        }

        class C {
            public function f($x): bool {
                return in_array($x, [Status::A, Status::B], true);
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('Status::equalsAny($x, Status::A, Status::B)', $judgment->warnings[0]->message);
    }

    public function test_repent_rewrites_in_array_to_equals_any(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum Status {
            use CompareSelf;
            case A;
            case B;
        }

        class C {
            public function f($x): bool { return in_array($x, [Status::A, Status::B], true); }
            public function g($x): bool { return in_array($x, [Status::A], true); }
        }
        PHP;

        $result = $this->prophet->repent('/x.php', $content);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('return Status::equalsAny($x, Status::A, Status::B);', $result->newContent);
        $this->assertStringContainsString('return Status::A->equals($x);', $result->newContent);
    }

    public function test_ignores_in_array_with_non_case_elements(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum Status {
            use CompareSelf;
            case A;
        }

        class C {
            public function f($x, $y): bool {
                return in_array($x, [Status::A, $y], true);
            }
        }
        PHP);

        $this->assertCount(0, $judgment->warnings);
    }

    // ────────────────────────────────────────────────────────────────
    // Re-anchoring an existing static call onto the literal case
    // ────────────────────────────────────────────────────────────────

    public function test_flags_static_equals_against_literal_case(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum PortKind {
            use CompareSelf;
            case Bag;
            case Single;
        }

        class Wiring {
            public function isBag($input): bool {
                return PortKind::equals($input->kind(), PortKind::Bag);
            }
        }
        PHP);

        // The wrong-form static call is a SIN (auto-fixable), not a soft nudge.
        $this->assertCount(1, $judgment->sins);
        $this->assertCount(0, $judgment->warnings);
        $this->assertStringContainsString('known case `PortKind::Bag`', $judgment->sins[0]->message);
        $this->assertStringContainsString('PortKind::Bag->equals($input->kind())', $judgment->sins[0]->suggestion);
    }

    public function test_flags_static_not_equals_against_literal_case(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum PortKind {
            use CompareSelf;
            case Bag;
        }

        class Wiring {
            public function notBag($input): bool {
                return PortKind::notEquals($input->kind(), PortKind::Bag);
            }
        }
        PHP);

        $this->assertCount(1, $judgment->sins);
        $this->assertStringContainsString('PortKind::Bag->notEquals($input->kind())', $judgment->sins[0]->suggestion);
    }

    public function test_does_not_flag_static_equals_between_two_dynamic_values(): void
    {
        // Neither operand is a literal case — there is no case to anchor on, so
        // the null-safe static form is the correct call. Leave it.
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum PortKind {
            use CompareSelf;
            case Bag;
        }

        class Wiring {
            public function same($a, $b): bool {
                return PortKind::equals($a, $b);
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_repent_reanchors_static_equals_onto_case(): void
    {
        $content = <<<'PHP'
        <?php
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum PortKind {
            use CompareSelf;
            case Bag;
        }

        class Wiring {
            public function isBag($input): bool { return PortKind::equals($input->kind(), PortKind::Bag); }
        }
        PHP;

        $result = $this->prophet->repent('/x.php', $content);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('return PortKind::Bag->equals($input->kind());', $result->newContent);
        $this->assertStringNotContainsString('PortKind::equals(', $result->newContent);
    }

    // ────────────────────────────────────────────────────────────────
    // Robustness
    // ────────────────────────────────────────────────────────────────

    public function test_handles_unparseable_file_gracefully(): void
    {
        $judgment = $this->prophet->judge('/x.php', '<?php this is { not valid');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
    }

    public function test_anchors_a_non_null_subject_and_is_not_re_flagged_by_anchor_prophet(): void
    {
        $body = <<<'PHP'
        namespace App;

        use App\Support\Enums\CompareSelf;

        enum Foo {
            use CompareSelf;
            case A;
            case B;
        }

        class Sieve {
            public function pick(Foo $kind): bool {
                return $kind === Foo::A || $kind === Foo::B;
            }
        }
        PHP;

        // The non-null subject anchors on the instance — NOT the static form, which
        // AnchorEnumComparisonProphet would immediately re-flag.
        $judgment = $this->judge($body);
        $this->assertStringContainsString('$kind->equalsAny(Foo::A, Foo::B)', $judgment->warnings[0]->message);

        $result = $this->prophet->repent('/x.php', "<?php\n" . $body);
        $code = (string) $result->newContent;
        $this->assertStringContainsString('return $kind->equalsAny(Foo::A, Foo::B);', $code);
        $this->assertStringNotContainsString('Foo::equalsAny($kind', $code);

        // The repented output is already anchored, so the sibling prophet stays silent.
        $anchor = new \JesseGall\CodeCommandments\Prophets\Backend\AnchorEnumComparisonProphet;
        $this->assertCount(0, $anchor->judge('/x.php', $code)->warnings);
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n" . $body);
    }

    // ────────────────────────────────────────────────────────────────
    // #57: validate the trait actually defines the configured methods
    // ────────────────────────────────────────────────────────────────

    public function test_does_not_autofix_when_the_trait_lacks_the_configured_method(): void
    {
        [$prophet, $file, $src] = $this->indexedTrait('public function is($o): bool { return true; } public function isNot($o): bool { return true; }');

        $judgment = $prophet->judge($file, $src);

        $this->assertTrue($judgment->hasWarnings());
        $this->assertFalse($judgment->warnings[0]->autoFixable, 'Trait uses is()/isNot(), not notEquals() — must not auto-fix.');
        $this->assertFalse($prophet->repent($file, $src)->absolved, 'repent must not emit an undefined-method call.');

        @unlink($file);
        @rmdir(dirname($file));
    }

    public function test_autofixes_when_the_trait_defines_the_configured_method(): void
    {
        [$prophet, $file, $src] = $this->indexedTrait('public function equals($o): bool { return true; } public function notEquals($o): bool { return true; }');

        $judgment = $prophet->judge($file, $src);

        $this->assertTrue($judgment->hasWarnings());
        $this->assertTrue($judgment->warnings[0]->autoFixable, 'Trait defines notEquals() — safe to auto-fix.');
        $this->assertTrue($prophet->repent($file, $src)->absolved);

        @unlink($file);
        @rmdir(dirname($file));
    }

    /**
     * @return array{0: SuggestCompareSelfTraitProphet, 1: string, 2: string}
     */
    public function test_does_not_rewrite_when_the_operand_is_the_backing_scalar(): void
    {
        // #66: equals(?Enum) TypeErrors on a string/int. Leave `===` when the
        // operand is the backing value (a string param or a string property),
        // but still rewrite when it is the enum itself.
        $dir = sys_get_temp_dir() . '/cc-cself66-' . uniqid();
        @mkdir($dir, 0755, true);

        file_put_contents($dir . '/Trait.php', "<?php\nnamespace App\\Enums;\ntrait ComparesSelf { public function equals(?ChannelType \$o): bool { return \$this === \$o; } }\n");
        $file = $dir . '/Svc.php';
        $src = "<?php\nnamespace App;\nuse App\\Enums\\ComparesSelf;\n"
            . "enum ChannelType: string { use ComparesSelf; case POS = 'pos'; case WEB = 'web'; }\n"
            . "class ShopChannel { public string \$type = ''; }\n"
            . "class Svc {\n"
            . " public function a(string \$channel): bool { return \$channel === ChannelType::POS; }\n"
            . " public function b(ShopChannel \$shopChannel): bool { return \$shopChannel->type === ChannelType::WEB; }\n"
            . " public function c(ChannelType \$t): bool { return \$t === ChannelType::POS; }\n"
            . "}\n";
        file_put_contents($file, $src);

        $index = \JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex::build([$dir . '/Trait.php', $file]);
        $prophet = new SuggestCompareSelfTraitProphet;
        $prophet->setCodebaseIndex($index);
        $prophet->configure(['trait' => 'App\\Enums\\ComparesSelf']);

        $judgment = $prophet->judge($file, $src);
        $this->assertCount(1, $judgment->warnings, 'Only the enum-typed operand (c) is flagged.');

        $repented = $prophet->repent($file, $src)->newContent;
        $this->assertStringContainsString('return $channel === ChannelType::POS;', $repented, 'string param left alone');
        $this->assertStringContainsString('return $shopChannel->type === ChannelType::WEB;', $repented, 'string property left alone');
        $this->assertStringContainsString('ChannelType::POS->equals($t)', $repented, 'enum operand rewritten');

        @unlink($dir . '/Trait.php');
        @unlink($file);
        @rmdir($dir);
    }

    public function test_does_not_rewrite_a_mixed_operand_or_one_compared_to_a_literal(): void
    {
        // #71: equals(?Enum) TypeErrors on mixed/string. Leave the === when the
        // operand is `mixed`, or when the SAME operand is also compared to a
        // scalar literal nearby (a strong "this is not the enum" signal).
        $dir = sys_get_temp_dir() . '/cc-cself71-' . uniqid();
        @mkdir($dir, 0755, true);

        file_put_contents($dir . '/Trait.php', "<?php\nnamespace App\\Enums;\ntrait ComparesSelf { public function equals(?ChannelType \$o): bool { return \$this === \$o; } }\n");
        $file = $dir . '/Svc.php';
        $src = "<?php\nnamespace App;\nuse App\\Enums\\ComparesSelf;\nuse Illuminate\\Support\\Arr;\n"
            . "enum ChannelType: string { use ComparesSelf; case POS = 'pos'; case WEB = 'web'; }\n"
            . "class Svc {\n"
            . " public function looksLikePos(array \$data): bool { \$channel = Arr::get(\$data, 'channel'); return \$channel === ChannelType::POS || \$channel === 'pos'; }\n"
            . " public function m(mixed \$x): bool { return \$x === ChannelType::POS; }\n"
            . " public function c(ChannelType \$t): bool { return \$t === ChannelType::POS; }\n"
            . "}\n";
        file_put_contents($file, $src);

        $index = \JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex::build([$dir . '/Trait.php', $file]);
        $prophet = new SuggestCompareSelfTraitProphet;
        $prophet->setCodebaseIndex($index);
        $prophet->configure(['trait' => 'App\\Enums\\ComparesSelf']);

        $repented = $prophet->repent($file, $src)->newContent;
        $this->assertStringContainsString("\$channel === ChannelType::POS || \$channel === 'pos'", $repented, 'literal-compared operand left alone');
        $this->assertStringContainsString('return $x === ChannelType::POS;', $repented, 'mixed operand left alone');
        $this->assertStringContainsString('ChannelType::POS->equals($t)', $repented, 'enum operand rewritten');

        @unlink($dir . '/Trait.php');
        @unlink($file);
        @rmdir($dir);
    }

    private function indexedTrait(string $traitMethods): array
    {
        $dir = sys_get_temp_dir() . '/cc-cself-' . uniqid();
        @mkdir($dir, 0755, true);

        file_put_contents($dir . '/CompareSelf.php', "<?php\nnamespace App\\Concerns;\ntrait CompareSelf { {$traitMethods} }\n");
        $file = $dir . '/Stuff.php';
        $src = "<?php\nnamespace App;\nuse App\\Concerns\\CompareSelf;\nenum ChannelType: string { use CompareSelf; case POS = 'pos'; case WEB = 'web'; }\nclass Page { public function f(ChannelType \$t): bool { return \$t !== ChannelType::POS; } }\n";
        file_put_contents($file, $src);

        $index = \JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex::build([$dir . '/CompareSelf.php', $file]);

        $prophet = new SuggestCompareSelfTraitProphet;
        $prophet->setCodebaseIndex($index);
        $prophet->configure(['trait' => 'App\\Concerns\\CompareSelf']);

        @unlink($dir . '/CompareSelf.php');

        return [$prophet, $file, $src];
    }
}
