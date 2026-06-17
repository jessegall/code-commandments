<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferTypeMethodOverInlineDispatchProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferTypeMethodOverInlineDispatchProphetTest extends TestCase
{
    private PreferTypeMethodOverInlineDispatchProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferTypeMethodOverInlineDispatchProphet;
    }

    // ── Detection 1: match/switch dispatch ──────────────────────────────

    public function test_flags_match_dispatching_per_enum_case(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        enum Op { case A; case B; case C; }

        class Calc {
            public function run(Op $op, $x): int {
                return match ($op) {
                    Op::A => $x + 1,
                    Op::B => $x - 1,
                    Op::C => $x * 2,
                };
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('match on $op', $judgment->warnings[0]->message);
        $this->assertStringContainsString('Op', $judgment->warnings[0]->message);
    }

    public function test_flags_switch_dispatching_per_enum_case(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        enum Op { case A; case B; }

        class Calc {
            public function run(Op $op): string {
                switch ($op) {
                    case Op::A: return 'a';
                    case Op::B: return 'b';
                }
                return '';
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('switch on $op', $judgment->warnings[0]->message);
    }

    public function test_does_not_flag_match_inside_the_enums_own_file(): void
    {
        // This IS the destination — a match ($this) on the enum's own cases.
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        enum Op {
            case A;
            case B;

            public function delta(int $x): int {
                return match ($this) {
                    Op::A => $x + 1,
                    Op::B => $x - 1,
                };
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_match_this_with_self_cases_inside_the_enum(): void
    {
        // Issue #11: `match ($this) { self::Mixed => … }` inside the enum IS the
        // per-case method the rule asks for — the cases resolve to a `self`
        // pseudo-FQCN, so the own-file skip must catch them too.
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        enum WireCategory
        {
            case Mixed;
            case ListOf;
            case Resource;

            public function token(): string
            {
                return match ($this) {
                    self::Mixed => 'mixed',
                    self::ListOf => 'list',
                    self::Resource => 'resource',
                };
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_match_true_guard(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        class C {
            public function f(int $n): string {
                return match (true) {
                    $n < 0 => 'neg',
                    default => 'pos',
                };
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_single_case_match(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        enum Op { case A; case B; }

        class C {
            public function f(Op $op, $x) {
                return match ($op) {
                    Op::A => $x,
                    default => null,
                };
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    // ── Detection 2: sentinel ternary on a value class ──────────────────

    public function test_flags_constant_substitution_ternary_on_value_class(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        final class WireType {
            public const string MIXED = 'mixed';
        }

        class Port {
            public function label(string $type): string {
                return $type === WireType::MIXED ? 'any' : $type;
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('WireType::MIXED', $judgment->warnings[0]->message);
        $this->assertStringContainsString('WireType::label', $judgment->warnings[0]->message);
    }

    public function test_flags_not_identical_substitution_ternary(): void
    {
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        final class WireType {
            public const string MIXED = 'mixed';
        }

        class Port {
            public function label(string $type): string {
                return $type !== WireType::MIXED ? $type : 'any';
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_does_not_flag_sentinel_ternary_on_enum_constant(): void
    {
        // Enum constants are the CompareSelf rule's territory.
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        enum Status { case Mixed; case Real; }

        class Port {
            public function label(Status $s, $fallback) {
                return $s === Status::Mixed ? 'any' : $s;
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_ternary_without_passthrough_branch(): void
    {
        // Neither branch is the subject — a plain either/or, not a substitution.
        $judgment = $this->judge(<<<'PHP'
        namespace App;

        final class WireType { public const string MIXED = 'mixed'; }

        class Port {
            public function label(string $type): string {
                return $type === WireType::MIXED ? 'any' : 'other';
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
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
