<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\PackageDetector;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractUseStatements;
use JesseGall\CodeCommandments\Support\Pipes\Php\FindManualHydration;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

/**
 * Flag hand-rolled array-to-object hydration — static creators (or inline
 * instantiations) that read array keys one by one and feed them into a
 * constructor. Spatie Laravel Data's `::from()` does all of that.
 */
#[IntroducedIn('1.18.0')]
class NoManualHydrationProphet extends PhpCommandment
{
    public function supported(): bool
    {
        return PackageDetector::hasSpatieData();
    }

    public function description(): string
    {
        return 'Do not hand-roll array-to-object hydration — extend Spatie Data and use ::from()';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Hand-rolling array-to-object hydration is reimplementing Spatie Laravel
Data, badly. A static fromArray() that reads keys one by one, type-checks
each value, and feeds new self(...) is dead code the moment the class
extends Data — ::from() does ALL of it.

Bad:
    final readonly class FieldSpec {
        public function __construct(
            public string $name,
            public string|null $label,
        ) {}

        public static function fromArray(array $row): self|null {
            $name = Arr::get($row, 'name');
            if (! is_string($name)) return null;
            $label = Arr::get($row, 'label');
            return new self(
                name: $name,
                label: is_string($label) ? $label : null,
            );
        }
    }

Good:
    final class FieldSpec extends Data {
        public function __construct(
            public readonly string $name,
            public readonly string|null $label = null,
        ) {}
    }

    $spec = FieldSpec::from($row);              // one row
    $specs = FieldSpec::collect($rows);          // list of rows

HOW SPATIE DATA REPLACES YOUR MANUAL CODE — read before writing any:

  - Key mapping is automatic: array keys map to constructor parameter
    names. snake_case input? Put #[MapInputName(SnakeCaseMapper::class)]
    on the class — do not rename keys by hand.
  - Type coercion is automatic: declared property types ARE the
    validation. string|null $label means a missing or null key becomes
    null. Defaults (= null, = true) replace every ?? fallback.
  - Nested structures hydrate themselves: a property typed as another
    Data class, or DataCollection<OtherData>, recursively hydrates from
    the nested arrays. Do not loop and hydrate children manually.
  - Genuinely dynamic values stay mixed: public mixed $default = null
    is fine — mixed is for values that ARE anything, not for skipping
    the type.
  - Custom coercion for ONE field: add a magic fromX creation method or
    a Cast — never a hand-written constructor wrapper for the whole class.
  - Tolerant decoding of untrusted input (LLM output, webhooks): make
    every property nullable with a default, hydrate, THEN validate the
    typed object. Wrap ::from() in try/catch when rejection is expected.

OBJECT-TO-OBJECT MAPPING — the same sin with properties instead of keys.

Building a foreign class field-by-field from one source object is the
mapping equivalent of fromArray():

Bad — mapping written at the call site:
    $fieldOutputs = array_map(
        static fn (OutputPort $port) => new OutputPort(
            name: 'value.' . $port->name,
            type: $port->type,
            nullable: $port->nullable,
            label: $port->label ?? $port->name,
            description: $port->description,
        ),
        $ports,
    );

Good — the mapping lives ON the target as a named factory:
    // In OutputPort:
    public static function passThrough(self $port, string $prefix = 'value.'): self
    {
        return new self(
            name: $prefix . $port->name,
            type: $port->type,
            nullable: $port->nullable,
            label: $port->label ?? $port->name,
            description: $port->description,
        );
    }

    $fieldOutputs = array_map(OutputPort::passThrough(...), $ports);

COPY-WITH-CHANGES — when source and target are the SAME type, re-listing
every constructor field to change one is a missing wither:

Bad:
    return new NodeDescriptor(
        key: $descriptor->key,
        kind: $descriptor->kind,
        label: $descriptor->label,
        // ...nine more copied fields...
        traceHandles: [...$descriptor->traceHandles, 'next'],
    );

Good — add a with(...) helper to the class (readonly and Spatie Data
classes alike):
    public function with(mixed ...$changes): static
    {
        $fields = get_object_vars($this);

        return new static(...[...$fields, ...$changes]);
    }

    return $descriptor->with(traceHandles: [...$descriptor->traceHandles, 'next']);

The exemption IS the rule: factories and withers themselves construct
from another instance's (or $this's) properties — inside the target
class that is righteous, because the mapping finally has one home.
Making the class a Spatie Data object helps the array boundary; the
wither is what kills the twelve-line copies.

Claude (and any other AI agent): do NOT write fromArray/fromRow/
fromMetadata static constructors that read keys manually, and do NOT
spell out another object's fields inside new Foo(...) at a call site.
If you are typing Arr::get more than once inside a static creator, or
forwarding three properties of the same object into a constructor,
stop — the hydration/mapping belongs on the target class. The prophet
detects both regardless of how parameters are annotated.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $pipe = (new FindManualHydration)
            ->withMinKeyReads((int) $this->config('min_key_reads', 2))
            ->withMinPropertyReads((int) $this->config('min_property_reads', 3));

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
            'object_mapping' => sprintf(
                'Manual mapping in %s — new %s built field-by-field from %s (%s properties: %s)',
                $groups['method'],
                $groups['target'],
                $groups['source'],
                $groups['count'],
                $groups['keys'],
            ),
            'object_copy' => sprintf(
                'Manual copy in %s — new %s re-lists %s fields of %s to change a few',
                $groups['method'],
                $groups['target'],
                $groups['count'],
                $groups['source'],
            ),
            default => sprintf(
                'Manual hydration in %s — %s array keys read by hand (%s)',
                $groups['method'],
                $groups['count'],
                $groups['keys'],
            ),
        };
    }

    /**
     * @param  array<string, string>  $groups
     */
    private function suggestionFor(array $groups): string
    {
        return match ($groups['kind']) {
            'object_mapping' => sprintf(
                'Move the mapping onto %s as a named factory — e.g. %s::passThrough(%s) or a purpose-named constructor — so it has one home and a name that says what it means.',
                $groups['target'],
                $groups['target'],
                $groups['source'],
            ),
            'object_copy' => sprintf(
                'Add a with(...) clone helper to %s (works on readonly and Spatie Data classes alike) so this becomes %s->with(changedField: ...) instead of re-listing every field.',
                $groups['target'],
                $groups['source'],
            ),
            default => 'Extend Spatie\LaravelData\Data and use ::from($row) / ::collect($rows) — '
                . 'Data auto-maps keys, coerces declared property types, and hydrates nested Data objects. '
                . 'Use #[MapInputName(...)] for key renames and nullable properties with defaults for tolerant decoding.',
        };
    }
}
