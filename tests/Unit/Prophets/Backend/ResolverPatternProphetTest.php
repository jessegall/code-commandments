<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\ResolverPatternProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class ResolverPatternProphetTest extends TestCase
{
    private ResolverPatternProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new ResolverPatternProphet;
    }

    public function test_flags_a_skip_or_match_chain_predicate_in_a_resolver(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class WireTypeResolver
        {
            public function resolvers(): array
            {
                return [
                    fn (string $t): ?string => str_starts_with($t, 'list:') ? $t : null,
                ];
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('str_starts_with', $judgment->warnings[0]->message);
        $this->assertStringContainsString('Predicate', $judgment->warnings[0]->message);
    }

    public function test_flags_explicit_bool_matcher_in_a_resolver(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class ThingResolver
        {
            public function resolvers(): array
            {
                return [
                    fn (string $field): bool => in_array($field, ['a', 'b'], true),
                ];
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('in_array', $judgment->warnings[0]->message);
    }

    public function test_domain_bound_predicate_points_at_the_resolvers_own_folder(): void
    {
        // in_array(self::SCALARS) reads a type's constants -> domain-bound, and
        // is not a kernel shape -> resolver's own folder.
        $judgment = $this->judge(<<<'PHP'
        class WireTypeResolver
        {
            public function resolvers(): array
            {
                return [
                    fn (string $t): ?string => in_array($t, self::SCALARS, true) ? $t : null,
                ];
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('Resolvers\\WireType\\Predicates', $judgment->warnings[0]->message);
    }

    public function test_generic_predicate_points_at_the_shared_folder(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class WireTypeResolver
        {
            public function resolvers(): array
            {
                return [
                    fn (string $t): ?string => in_array($t, ['a', 'b'], true) ? $t : null,
                ];
            }
        }
        PHP);

        $this->assertStringContainsString('shared `Resolvers\\Predicates`', $judgment->warnings[0]->message);
    }

    public function test_reuses_a_kernel_predicate_when_one_fits(): void
    {
        // A null/instanceof/str_starts_with test maps to an existing kernel
        // Predicate — reuse it, don't create a new class.
        $judgment = $this->judge(<<<'PHP'
        class TokenResolver
        {
            public function resolvers(): array
            {
                return [
                    fn (?string $t): ?string => str_starts_with((string) $t, 'list:') ? $t : null,
                ];
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('HasPrefix', $judgment->warnings[0]->message);
        $this->assertStringContainsString('reuse the kernel', $judgment->warnings[0]->message);
    }

    public function test_does_not_flag_a_value_transform_ternary(): void
    {
        // Both branches are values — a map, not a skip-or-match predicate.
        $judgment = $this->judge(<<<'PHP'
        class ThingResolver
        {
            public function resolvers(): array
            {
                return [
                    fn ($entry) => $entry instanceof self ? $entry : self::from($entry),
                ];
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_predicates_outside_a_resolver_class(): void
    {
        // Same closure shape, but the class is not a resolver.
        $judgment = $this->judge(<<<'PHP'
        class WorkflowSettings
        {
            public function build(): array
            {
                return [
                    fn (string $t): ?string => str_starts_with($t, 'list:') ? $t : null,
                ];
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_non_predicate_ternary_condition(): void
    {
        // The null branch is present, but the condition is a plain truthiness
        // check, not a predicate worth extracting.
        $judgment = $this->judge(<<<'PHP'
        class ThingResolver
        {
            public function resolvers(): array
            {
                return [
                    fn ($x): ?string => $x ? 'yes' : null,
                ];
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_base_class_config_marks_a_resolver(): void
    {
        $this->prophet->configure([
            'suffix' => '',
            'base_classes' => ['App\\Support\\Resolvers\\Resolver'],
        ]);

        $judgment = $this->judge(<<<'PHP'
        class Whatever extends Resolver
        {
            public function resolvers(): array
            {
                return [
                    fn (string $t): ?string => str_contains($t, 'x') ? $t : null,
                ];
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_flags_if_guard_predicate_in_resolver(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class VisibilityResolver
        {
            public function resolve($rule)
            {
                if ($rule === null) { return true; }
                if (is_array($rule)) { return $this->fromArray($rule); }
                return null;
            }
        }
        PHP);

        $this->assertCount(2, $judgment->warnings);
    }

    public function test_flags_match_true_arm_predicate_in_resolver(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class VerdictResolver
        {
            public function resolve($input, $sourceType): bool
            {
                return match (true) {
                    $input->type === Wire::MIXED || $sourceType === null => true,
                    default => false,
                };
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('Wire::MIXED', $judgment->warnings[0]->message);
    }

    public function test_does_not_flag_var_equals_var_comparison(): void
    {
        // Two runtime values — not a reusable named predicate.
        $judgment = $this->judge(<<<'PHP'
        class NodeResolver
        {
            public function resolve($node, $sourceNodeId)
            {
                if ($node->id === $sourceNodeId) { return $node; }
                return null;
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_nominates_a_resolver_for_a_first_match_dispatch_chain(): void
    {
        // A value object with a parse() that is a chain of predicate guards each
        // returning a constructed value — a resolver in disguise.
        $judgment = $this->judge(<<<'PHP'
        class WireType
        {
            public static function parse(?string $token): self
            {
                if ($token === null) { return self::mixed(); }
                if (str_starts_with($token, 'resource:')) { return self::resource($token); }
                if (str_starts_with($token, 'list:')) { return self::listOf($token); }
                if (in_array($token, ['string', 'int'], true)) { return self::scalar($token); }
                return self::classRef($token);
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('first-match dispatch chain', $judgment->warnings[0]->message);
        $this->assertStringContainsString('Resolvers\\WireType\\WireTypeResolver', $judgment->warnings[0]->message);
    }

    public function test_does_not_nominate_when_fewer_than_three_guards(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class WireType
        {
            public static function parse(?string $token): self
            {
                if ($token === null) { return self::mixed(); }
                if (str_starts_with($token, 'list:')) { return self::listOf($token); }
                return self::classRef($token);
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_nominate_when_returns_are_not_all_constructions(): void
    {
        // One branch returns a plain value, not a construction — not pure dispatch.
        $judgment = $this->judge(<<<'PHP'
        class Thing
        {
            public function pick(?string $token): mixed
            {
                if ($token === null) { return self::a(); }
                if (str_starts_with($token, 'x')) { return self::b(); }
                if (str_contains($token, 'y')) { return self::c(); }
                return $token;
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_sins_an_ugly_resolver_of_inline_closures(): void
    {
        // >= 3 chain entries that still inline a predicate closure — the
        // half-done extraction. A sin, not a nudge.
        $judgment = $this->judge(<<<'PHP'
        class WireTypeResolver extends Resolver
        {
            protected function resolvers(): iterable
            {
                return [
                    static fn (string $t): ?WireType => $t === WireType::MIXED ? WireType::mixed() : null,
                    static fn (string $t): ?WireType => str_starts_with($t, 'list:') ? WireType::listOf($t) : null,
                    static fn (string $t): ?WireType => str_starts_with($t, 'res:') ? WireType::resource($t) : null,
                    static fn (string $t): ?WireType => in_array($t, ['a','b'], true) ? WireType::scalar($t) : null,
                ];
            }
        }
        PHP);

        $this->assertCount(1, $judgment->sins);
        $this->assertCount(0, $judgment->warnings);
        $this->assertStringContainsString('inline predicate closures', $judgment->sins[0]->message);
    }

    public function test_flags_match_true_dispatch_as_a_resolver(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Registry
        {
            public function expand($d): ?NodeDescriptor
            {
                return match (true) {
                    $d->key === InputBagNode::KEY => $this->expandBag($d),
                    str_starts_with($d->key, 'x') => $this->expandX($d),
                    in_array($d->key, ['a','b'], true) => $this->expandList($d),
                    default => null,
                };
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('dispatch chain', $judgment->warnings[0]->message);
    }

    public function test_flags_bool_chain_as_a_composite_predicate(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class TypeCompatibility
        {
            public function compatible(string $source, string $target): bool
            {
                if ($source === 'mixed') { return true; }
                if (str_starts_with($target, 'list:')) { return false; }
                if (in_array($source, ['a','b'], true)) { return true; }
                return false;
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('composite Predicate', $judgment->warnings[0]->message);
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
