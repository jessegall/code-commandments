<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractUseStatements;
use JesseGall\CodeCommandments\Support\Pipes\Php\FindArrayBagDeclarations;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

/**
 * Flag `array<string, mixed>` bags travelling through signatures —
 * parameters, properties (incl. constructor-promoted), and returns.
 * A named value bag deserves a Fluent-based value class; on a Spatie
 * Data object the Castable/WithCastable combo hydrates it for free.
 */
#[IntroducedIn('1.22.0')]
class NoArrayBagProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Do not pass array<string, mixed> bags around — give the bag a Fluent value class';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Structural;
    }

    /**
     * Giving the bag a value class removes the very `$bag['key']` access
     * that NoArrayStringIndexing flags — so the bag is the root cause and
     * its string-indexing symptoms are deferred until it is resolved.
     *
     * @return list<class-string>
     */
    public function supersedes(): array
    {
        return [NoArrayStringIndexingProphet::class];
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
An `array<string, mixed>` parameter, property, or return is a value bag
travelling without a type. Every consumer re-derives its shape with
`$bag['key'] ?? null`, `Arr::get(...)`, and `array_key_exists(...)`,
and the logic that belongs to the bag scatters across its callers.

Give the bag a class. `Illuminate\Support\Fluent` already provides the
request-style accessors — `get()`, `has()`, `string()`, `boolean()`,
`integer()`, `enum()`, `toArray()` — so the value class is two lines:

Bad:
    /**
     * @param  array<string, mixed>  $staticInputs
     */
    public function resolveFor(InputPort $port, array $staticInputs): bool {
        $value = $staticInputs[$name] ?? null;
        // ...comparison helpers living in the wrong class...
    }

Good:
    final class StaticInputs extends Fluent
    {
        /**
         * Whether every given sibling input matches its expected value.
         *
         * @param  array<string, mixed>  $expected
         */
        public function matches(array $expected): bool { /* ... */ }
    }

    public function resolveFor(InputPort $port, StaticInputs $inputs): bool {
        return $inputs->matches($rule);
    }

Note where the bag-shaped logic went: ONTO the bag. Comparison,
defaulting, and validation helpers that took the array as a parameter
become methods of the value class — they finally have one home.

THE CASTABLE TRICK — when the bag is a property of a Spatie Data class,
implement `Castable` so raw input hydrates straight into the typed bag:

    final class NodeMetadata extends Fluent implements Castable
    {
        public static function dataCastUsing(mixed ...$arguments): Cast
        {
            return new class implements Cast
            {
                public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): NodeMetadata
                {
                    if ($value instanceof NodeMetadata) {
                        return $value;
                    }

                    return new NodeMetadata(is_array($value) ? $value : []);
                }
            };
        }
    }

    final class WorkflowNode extends Data
    {
        public function __construct(
            public readonly string $id,
            #[WithCastable(NodeMetadata::class)]
            public readonly NodeMetadata $metadata = new NodeMetadata,
        ) {}
    }

`WorkflowNode::from($json)` now produces a typed `NodeMetadata` with no
manual mapping anywhere — the cast accepts both raw arrays and already-
hydrated instances, so the same Data class works from JSON and from code.

CALL-SITE IDIOMS — once the bag is typed, the array idioms map directly:

    array_key_exists($name, $node->staticInputs)  ->  $node->staticInputs->has($name)
    $staticInputs[$name] ?? null                  ->  $inputs->get($name)
    Arr::get($metadata, $key->value, $default)    ->  $metadata->key($key, $default)
    (serialization boundary)                      ->  $bag->toArray()

For enum-keyed bags, add a typed lookup so the `->value` plumbing lives
in one place:

    public function key(NodeMetadataKey $key, mixed $default = null): mixed
    {
        return $this->get($key->value, $default);
    }

WHEN EXTENDING FLUENT COLLIDES — Fluent is method-heavy (`get()`,
`value()`, `all()`, `scope()`, ...) and its magic `__call` doubles as a
dynamic attribute setter. If the bag's domain vocabulary clashes with
one of those names, do not contort the domain: compose a Fluent inside
the class and forward to it:

    final class PortValues
    {
        /** @var Fluent<string, mixed> */
        private readonly Fluent $attributes;

        /**
         * @param  array<string, mixed>  $attributes
         */
        public function __construct(array $attributes = [])
        {
            $this->attributes = new Fluent($attributes);
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->attributes->get($key, $default);
        }

        public function has(string $key): bool
        {
            return $this->attributes->has($key);
        }

        // `value()` is free to mean what the DOMAIN needs it to mean.
        public function value(PortRef $ref): mixed { /* ... */ }

        /**
         * @return array<string, mixed>
         */
        public function toArray(): array
        {
            return $this->attributes->toArray();
        }
    }

Forward explicitly (a handful of one-liners) rather than via a blanket
`__call` — the public surface stays typed and discoverable. The
Castable trick composes the same way: `dataCastUsing()` just news up
the wrapper instead of the Fluent subclass.

WHAT REMAINS RIGHTEOUS:

  - Genuine dictionaries with a concrete value type — `array<string,
    PortRef>`, `array<string, list<int>>` — are real maps, not records
    in disguise. Name the value type and the prophet stays silent.
    Annotating `array<string, mixed>` is NOT an opt-out.
  - Nested structures: only a TOP-LEVEL bag type is flagged. A type like
    `array<string, array<string, mixed>>` or `list<array<string, mixed>>`
    is a container of dynamic payloads — and a class wrapping such
    storage behind get()/set()/has() accessors (a ScopeFrames-style
    hand-rolled bag abstraction) is the goal, not the sin.
  - The bag class itself — whether it extends Fluent or composes one —
    takes raw arrays at its constructor and helpers because it IS the
    array boundary.
  - Serialization signatures: toArray(), jsonSerialize(), and vendor-
    interface methods like Cast::cast() keep their array shapes.

Claude (and any other AI agent): when a constructor property, parameter,
or return is annotated `array<string, mixed>`, do NOT add another one
next to it — introduce (or reuse) a `Fluent`-based bag class, move the
bag-shaped helpers onto it, and on Spatie Data classes wire it with
`Castable` + `#[WithCastable(...)]` so hydration stays automatic.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $pipe = (new FindArrayBagDeclarations)
            ->withExemptMethods((array) $this->config('exempt_methods', []));

        return PhpPipeline::make($filePath, $content)
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractUseStatements::class)
            ->pipe($pipe)
            ->sinsFromMatches(
                fn ($match) => $this->messageFor($match->groups),
                fn ($match) => $this->suggestionFor($match->groups),
            )
            ->judge();
    }

    /**
     * @param  array<string, string>  $groups
     */
    private function messageFor(array $groups): string
    {
        return match ($groups['kind']) {
            'data_property', 'property' => sprintf(
                'Property $%s of %s is a raw %s bag',
                $groups['name'],
                $groups['owner'],
                $groups['annotation'],
            ),
            'return' => sprintf(
                '%s() returns a raw %s bag',
                $groups['name'],
                $groups['annotation'],
            ),
            default => sprintf(
                'Parameter $%s of %s() is a raw %s bag',
                $groups['name'],
                $groups['owner'],
                $groups['annotation'],
            ),
        };
    }

    /**
     * @param  array<string, string>  $groups
     */
    private function suggestionFor(array $groups): string
    {
        return match ($groups['kind']) {
            'data_property' => sprintf(
                'Create `final class %s extends Fluent implements Castable` with a dataCastUsing() Cast, '
                . 'then declare `#[WithCastable(%s::class)] public readonly %s $%s = new %s` — '
                . 'raw input hydrates straight into the typed bag and bag logic gets a home.',
                $groups['target'],
                $groups['target'],
                $groups['target'],
                $groups['name'],
                $groups['target'],
            ),
            'property' => sprintf(
                'Create `final class %s extends Fluent` and type the property as %s — '
                . 'callers swap subscripts for get()/has()/string()/enum() and bag helpers become methods on the class.',
                $groups['target'],
                $groups['target'],
            ),
            'return' => sprintf(
                'Return a Fluent-based bag class (e.g. `final class %s extends Fluent`) instead of a raw array — '
                . 'or, if the keys are a fixed set, a Spatie Data object.',
                $groups['target'],
            ),
            default => sprintf(
                'Accept a `final class %s extends Fluent` bag class instead of the raw array — '
                . 'the helpers that consume this parameter likely belong on the bag itself.',
                $groups['target'],
            ),
        };
    }
}
