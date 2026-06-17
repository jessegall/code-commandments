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


    public function test_sins_a_composed_resolver_of_inline_closures(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class WireType
        {
            public static function parse(?string $token): self
            {
                return Resolver::firstResultWins(
                    fn (?string $t): ?self => $t === self::MIXED ? self::mixed() : null,
                    fn (string $t): ?self => str_starts_with($t, 'list:') ? self::listOf($t) : null,
                    fn (string $t): ?self => str_starts_with($t, 'res:') ? self::resource($t) : null,
                    fn (string $t): ?self => in_array($t, ['a', 'b'], true) ? self::scalar($t) : null,
                )->resolve($token) ?? self::classRef($token);
            }
        }
        PHP);

        $this->assertCount(1, $judgment->sins);
        $this->assertStringContainsString('inline predicate closures', $judgment->sins[0]->message);
    }

    public function test_flags_a_single_inline_closure_entry_as_a_warning(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C
        {
            public function r($x)
            {
                return Resolver::firstResultWins(
                    fn (string $t): ?string => str_starts_with($t, 'list:') ? $t : null,
                )->resolve($x);
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('named Predicate', $judgment->warnings[0]->message);
    }

    public function test_flags_a_forwarding_closure_entry(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C
        {
            public function r($x)
            {
                return Resolver::collect(
                    fn ($req) => $this->fieldCandidate($req),
                )->resolve($x);
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('first-class callable', $judgment->warnings[0]->message);
        $this->assertStringContainsString('$this->fieldCandidate(...)', $judgment->warnings[0]->message);
    }

    public function test_does_not_flag_named_predicate_entries(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C
        {
            public function r($x)
            {
                return Resolver::firstResultWins(
                    IsNull::make()->when(fn () => 'a'),
                    HasPrefix::of('list:')->when(fn ($t) => 'b'),
                )->resolve($x);
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_value_transform_entry(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C
        {
            public function r($x)
            {
                return Resolver::firstResultWins(
                    fn ($entry) => $entry instanceof self ? $entry : self::from($entry),
                )->resolve($x);
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_one_off_helper_booleans_in_a_named_only_resolver(): void
    {
        // A *Resolver-named service that does NOT extend the base is not a chain
        // resolver — its helper booleans must not be policed (that led agents to
        // wrap every `$x === null` in a predicate object).
        $judgment = $this->judge(<<<'PHP'
        class ConnectCandidateResolver
        {
            public function compatible($source, $target): bool
            {
                if ($source === null) { return true; }
                return $source === $target;
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_flags_a_scattered_predicate_invocation(): void
    {
        // Instantiating a Predicate and invoking it inline for a single check.
        $judgment = $this->judge(<<<'PHP'
        use App\Support\Resolvers\Predicates\IsNull;

        class Service
        {
            public function go($source): bool
            {
                return (new IsNull())($source);
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('instantiated and invoked inline', $judgment->warnings[0]->message);
        $this->assertStringContainsString('IsNull', $judgment->warnings[0]->message);
    }

    public function test_flags_a_scattered_predicate_via_a_local_variable(): void
    {
        // `$p = new IsNull(); $p($x)` — laundering the inline invocation through
        // a variable is the same smell.
        $judgment = $this->judge(<<<'PHP'
        use App\Support\Resolvers\Predicates\IsNull;

        class TypeService
        {
            public function compatible($source, $target): bool
            {
                $isNull = new IsNull();
                if ($isNull($source) || $isNull($target)) { return true; }
                return false;
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('IsNull', $judgment->warnings[0]->message);
    }

    public function test_does_not_flag_a_predicate_passed_as_a_callback(): void
    {
        // Passing a predicate as a filter callback is legit — it is used, not
        // instantiated-and-invoked for one check.
        $judgment = $this->judge(<<<'PHP'
        use App\Support\Resolvers\Predicates\IsControlInSocket;

        class Service
        {
            public function handle($inputs)
            {
                return Option::first($inputs, new IsControlInSocket());
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_predicate_used_as_a_chain_entry(): void
    {
        // `new IsNull()->when(...)` is a chain entry (method call), not an
        // inline invocation — that is the CORRECT usage.
        $judgment = $this->judge(<<<'PHP'
        use App\Support\Resolvers\Predicates\IsNull;

        class WireTypeResolver extends Resolver
        {
            protected function resolvers(): iterable
            {
                return [
                    new IsNull()->when(fn () => WireType::mixed()),
                ];
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
        $this->assertStringContainsString('Resolver::firstResultWins', $judgment->warnings[0]->message);
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
