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
        $this->assertStringContainsString('isOneOf', $judgment->warnings[0]->message);
        $this->assertStringNotContainsString('[ADOPT]', $judgment->warnings[0]->message);
        $this->assertStringContainsString('NodeKind::Input', $judgment->warnings[0]->message);
        $this->assertStringContainsString('NodeKind::Output', $judgment->warnings[0]->message);
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
        $this->assertStringContainsString('isNotOneOf', $judgment->warnings[0]->message);
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
        $this->assertStringContainsString('3-chain', $judgment->warnings[0]->message);
        $this->assertStringContainsString('Foo::A, Foo::B, Foo::C', $judgment->warnings[0]->message);
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
        $this->assertStringContainsString('$descriptor->kind->isOneOf', $judgment->warnings[0]->message);
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

    public function test_ignores_single_comparison(): void
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

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_ignores_different_lhs(): void
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

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_ignores_mixed_enum_chain(): void
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

        $this->assertTrue($judgment->isRighteous());
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

    public function test_ignores_or_chain_with_non_identical_atom(): void
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

        $this->assertTrue($judgment->isRighteous());
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
            'is_one_of_method' => 'isAny',
            'is_not_one_of_method' => 'isNone',
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
        $this->assertStringContainsString('->isAny(', $judgment->warnings[0]->message);
        $this->assertStringNotContainsString('isOneOf', $judgment->warnings[0]->message);
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

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n" . $body);
    }
}
