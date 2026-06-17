<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferExtractedPredicateProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferExtractedPredicateProphetTest extends TestCase
{
    private PreferExtractedPredicateProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferExtractedPredicateProphet;
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
        $judgment = $this->judge(<<<'PHP'
        class WireTypeResolver
        {
            public function resolvers(): array
            {
                return [
                    fn (string $t): ?string => str_starts_with($t, self::LIST_PREFIX) ? $t : null,
                ];
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        // References self::LIST_PREFIX -> domain-bound -> resolver's own folder.
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
                    fn (string $t): ?string => str_starts_with($t, 'list:') ? $t : null,
                ];
            }
        }
        PHP);

        $this->assertStringContainsString('shared Resolvers\\Predicates', $judgment->warnings[0]->message);
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
