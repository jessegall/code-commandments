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

    public function test_flags_prefix_substr_inside_when(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C
        {
            public function r($x)
            {
                return Resolver::firstResultWins(
                    HasPrefix::of('list:')->then(static fn (string $t) => self::listOf(substr($t, strlen('list:')))),
                )->resolve($x);
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('transform', $judgment->warnings[0]->message);
        $this->assertStringContainsString('StripPrefix', $judgment->warnings[0]->message);
    }

    public function test_does_not_flag_named_predicate_entries(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C
        {
            public function r($x)
            {
                return Resolver::firstResultWins(
                    IsNull::make()->then(fn () => 'a'),
                    HasPrefix::of('list:')->then(fn ($t) => 'b'),
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
        // `new IsNull()->then(...)` is a chain entry (method call), not an
        // inline invocation — that is the CORRECT usage.
        $judgment = $this->judge(<<<'PHP'
        use App\Support\Resolvers\Predicates\IsNull;

        class WireTypeResolver extends Resolver
        {
            protected function resolvers(): iterable
            {
                return [
                    new IsNull()->then(fn () => WireType::mixed()),
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

    public function test_does_not_nominate_a_validity_gate_with_a_shared_fallback(): void
    {
        // Issue #17: the guards all early-return the SAME fallback expression —
        // a validity gate, not three distinct predicate->factory alternatives.
        $judgment = $this->judge(<<<'PHP'
        class TriggerEventFactory
        {
            public function create(?string $eventKey, ValueBag $payload): object
            {
                if ($eventKey === null) { return $this->stdClass($payload); }
                if (! class_exists($eventKey)) { return $this->stdClass($payload); }
                if (! is_subclass_of($eventKey, Event::class)) { return $this->stdClass($payload); }
                return $this->build($eventKey, $payload);
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_nominate_multi_input_sufficiency_guards(): void
    {
        // #34: each guard tests a different relationship between TWO inputs
        // (source + target). That is sequential sufficiency, not one-input
        // first-match dispatch — a Resolver over a single input can't model it.
        $judgment = $this->judge(<<<'PHP'
        class TypeCompatibility
        {
            public function compatible(Type $source, Type $target): bool
            {
                if ($source->isAny() || $target->isAny()) { return true; }
                if ($source->name === $target->name) { return true; }
                if ($source->isList() && $target->isList()) { return true; }
                return false;
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_nominate_an_instance_procedure_calling_this(): void
    {
        // #34: sequential validation that dispatches to the object's own
        // methods (`$this->checkElements`). The prescribed resolver uses named
        // static factories / Predicates, not `$this->` collaborators.
        $judgment = $this->judge(<<<'PHP'
        class SchemaContractValidator
        {
            public function checkValue(Field $field, mixed $value): array
            {
                if ($field->many === true) { return ['not a list']; }
                if ($field->required === true) { return ['missing']; }
                if ($field->nested === true) { return $this->checkElements($value); }
                return [];
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_nominate_a_reflection_introspection_helper(): void
    {
        // #34: a >=3-guard helper that introspects a Reflection* value is
        // infrastructure glue, not a reusable domain Predicate.
        $judgment = $this->judge(<<<'PHP'
        class SocketReflector
        {
            public function propertyAllowsNull(\ReflectionType $type): bool
            {
                if ($type instanceof \ReflectionNamedType && $type->allowsNull()) { return true; }
                if ($type instanceof \ReflectionUnionType) { return true; }
                if (! $type instanceof \ReflectionNamedType) { return true; }
                return false;
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_nominate_a_reflection_procedure_with_try_catch(): void
    {
        // Issue #17: a procedure that transforms/throws inside a try/catch is
        // the scripture's documented carve-out — not a dispatch chain.
        $judgment = $this->judge(<<<'PHP'
        class TriggerEventFactory
        {
            public function create(?string $eventKey, ValueBag $payload): object
            {
                if ($eventKey === null) { return $this->stdClass($payload); }
                if (! class_exists($eventKey)) { return $this->fallback($payload); }
                if (! is_subclass_of($eventKey, Event::class)) { return $this->other($payload); }
                try {
                    $ref = new \ReflectionClass($eventKey);
                    return $ref->newInstanceArgs($payload->all());
                } catch (\Throwable $e) {
                    return $this->stdClass($payload);
                }
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
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
        class TypeCheck
        {
            public function isExotic(string $token): bool
            {
                if ($token === 'mixed') { return true; }
                if (str_starts_with($token, 'list:')) { return false; }
                if (in_array($token, ['a','b'], true)) { return true; }
                return false;
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('composite Predicate', $judgment->warnings[0]->message);
    }

    public function test_flags_repeated_inline_then_factory_closures(): void
    {
        // >= 3 entries each inlining a domain factory closure -> suggest named
        // invokable factory classes under Support\Resolvers\Factories.
        $judgment = $this->judge(<<<'PHP'
        class Expander
        {
            public function expand($request)
            {
                return Resolver::firstResultWins(
                    KeyIs::of('a')->then(fn ($r) => $this->expandA($r->descriptor, $r->node)),
                    KeyIs::of('b')->then(fn ($r) => $this->expandB($r->descriptor, $r->node)),
                    KeyIs::of('c')->then(fn ($r) => $this->expandC($r->descriptor, $r->node)),
                )->resolve($request);
            }
        }
        PHP);

        $messages = array_map(fn ($w) => $w->message, $judgment->warnings);
        $hit = array_filter($messages, fn ($m) => str_contains($m, 'inline `->then()` factory closures'));
        $this->assertNotEmpty($hit);
        $this->assertStringContainsString('Support\\Resolvers\\Factories', implode("\n", $messages));
    }

    public function test_does_not_flag_a_couple_of_inline_then_factories(): void
    {
        // Below the threshold — a one-off inline factory is fine, not nagged.
        $judgment = $this->judge(<<<'PHP'
        class C
        {
            public function go($req)
            {
                return Resolver::firstResultWins(
                    KeyIs::of('a')->then(fn ($r) => $this->buildA($r->x)),
                    KeyIs::of('b')->then(fn ($r) => $this->buildB($r->x)),
                )->resolve($req);
            }
        }
        PHP);

        $messages = implode("\n", array_map(fn ($w) => $w->message, $judgment->warnings));
        $this->assertStringNotContainsString('inline `->then()` factory closures', $messages);
    }

    public function test_flags_repeated_doubled_strip_prefix_entries(): void
    {
        // >= 2 entries declaring the prefix twice (HasPrefix + StripPrefix) ->
        // suggest a domain Resolver decorator with a stripPrefix() builder.
        $judgment = $this->judge(<<<'PHP'
        class WireType
        {
            public static function parse(?string $token): self
            {
                return Resolver::firstResultWins(
                    Equals::to(self::MIXED)->then(self::mixed(...)),
                    HasPrefix::of(self::RESOURCE_PREFIX)->transform(StripPrefix::of(self::RESOURCE_PREFIX))->then(self::resource(...)),
                    HasPrefix::of(self::LIST_PREFIX)->transform(StripPrefix::of(self::LIST_PREFIX))->then(self::listOf(...)),
                    HasPrefix::of(self::SCHEMA_PREFIX)->transform(StripPrefix::of(self::SCHEMA_PREFIX))->then(self::schema(...)),
                )->resolve($token);
            }
        }
        PHP);

        $messages = implode("\n", array_map(fn ($w) => $w->message, $judgment->warnings));
        $this->assertStringContainsString('declared TWICE per entry', $messages);
        $this->assertStringContainsString('ResolverDecorator', $messages);
    }

    public function test_does_not_flag_a_single_strip_prefix_entry(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class C
        {
            public function go($token)
            {
                return Resolver::firstResultWins(
                    HasPrefix::of('list:')->transform(StripPrefix::of('list:'))->then(fn ($x) => X::of($x)),
                    IsScalarToken::make()->then(Y::scalar(...)),
                )->resolve($token);
            }
        }
        PHP);

        $messages = implode("\n", array_map(fn ($w) => $w->message, $judgment->warnings));
        $this->assertStringNotContainsString('declared TWICE per entry', $messages);
    }

    public function test_does_not_crash_on_first_class_callables(): void
    {
        // Issue #18: getArgs() asserts on a first-class callable. A forwarding
        // arrow whose body is an FCC, and a Resolver call in FCC form, must be
        // handled, not crash.
        $judgment = $this->judge(<<<'PHP'
        class Mapper
        {
            public function run(array $nodes): array
            {
                $a = array_map($this->transform(...), $nodes);
                $r = Resolver::firstResultWins(fn () => $this->keep(...));

                return $a;
            }
        }
        PHP);

        $this->assertNotNull($judgment);
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
