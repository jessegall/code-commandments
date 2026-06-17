<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoConditionalArraySpreadProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoConditionalArraySpreadProphetTest extends TestCase
{
    private NoConditionalArraySpreadProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoConditionalArraySpreadProphet;
    }

    public function test_flags_ternary_spread_with_empty_array_literal_arm(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C {
            public function build(?string $label): array {
                return [
                    'name' => 'x',
                    ...($label === null ? [] : ['label' => $label]),
                ];
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('T_Array::from', $judgment->warnings[0]->message);
    }

    public function test_flags_when_the_empty_arm_is_the_truthy_branch(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C {
            public function build(bool $has, $options): array {
                return [
                    'name' => 'x',
                    ...($has ? ['options' => $options] : []),
                ];
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_flags_t_array_empty_call_arm(): void
    {
        $judgment = $this->judge(<<<'PHP'
        use JesseGall\PhpTypes\T_Array;

        class C {
            public function build(?string $label): array {
                return [
                    'name' => 'x',
                    ...($label === null ? T_Array::empty() : ['label' => $label]),
                ];
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_flags_t_array_empty_constant_arm(): void
    {
        $judgment = $this->judge(<<<'PHP'
        use JesseGall\PhpTypes\T_Array;

        class C {
            public function build(bool $has, $v): array {
                return ['a' => 1, ...($has ? ['b' => $v] : T_Array::EMPTY)];
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_flags_short_ternary_with_empty_arm(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C {
            public function build(array $maybe): array {
                return ['a' => 1, ...($maybe ?: [])];
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_flags_each_distinct_conditional_spread(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C {
            public function build(?string $label, bool $has, $opts): array {
                return [
                    'name' => 'x',
                    ...($label === null ? [] : ['label' => $label]),
                    ...($has ? ['options' => $opts] : []),
                ];
            }
        }
        PHP);

        $this->assertCount(2, $judgment->warnings);
    }

    public function test_does_not_flag_a_plain_spread(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C {
            public function build(array $extra): array {
                return ['a' => 1, ...$extra];
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_null_coalesce_spread(): void
    {
        // `...($extra ?? [])` merges an opaque variable array, not a hand-listed
        // key gated by a condition — left alone.
        $judgment = $this->judge(<<<'PHP'
        class C {
            public function build(?array $extra): array {
                return ['a' => 1, ...($extra ?? [])];
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_ternary_spread_without_an_empty_arm(): void
    {
        // Both arms are non-empty arrays — a genuine either/or merge, not the
        // optional-key idiom.
        $judgment = $this->judge(<<<'PHP'
        class C {
            public function build(bool $flag, array $a, array $b): array {
                return ['x' => 1, ...($flag ? $a : $b)];
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
