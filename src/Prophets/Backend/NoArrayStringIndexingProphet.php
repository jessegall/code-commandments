<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractUseStatements;
use JesseGall\CodeCommandments\Support\Pipes\Php\FindArrayStringIndexing;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

/**
 * Flag array subscript accesses that treat a PHP array like an object.
 *
 * When the key is a known-at-write-time string (literal, class constant,
 * enum case `->value`), the array is a structured record in disguise.
 * Wrap it in a DTO or value object so reads become typed property access.
 *
 * When a `CodebaseIndex` is injected (same-scroll call graph), per-sin
 * suggestions walk upstream through `max_trace_depth` hops to point at
 * the method where the DTO should actually be introduced.
 *
 *
 *
 *
 * @method-generated-start
 * @method static crossFileTrace(bool $on = true)
 * @method static maxTraceDepth(int $value)
 * @method-generated-end
 */
#[IntroducedIn('1.4.0')]
class NoArrayStringIndexingProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    private ?CodebaseIndex $codebaseIndex = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->codebaseIndex = $index;
    }

    protected function defaultTier(): Tier
    {
        return Tier::Structural;
    }

    public function description(): string
    {
        return 'Prefer typed DTOs over string-indexed arrays for structured data';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Array string indexing on structured data is a "PHP array as poor-man's
object" pattern. Every `$row['nodeId']`, `$config['label']`,
`$data['key']`, `$arr[SomeEnum::Case->value]` access is type-unsafe,
IDE-unfriendly, and rename-hostile. Wrap the structure in a typed class
the moment it enters your code.

Bad:
    $nodeId = $row['nodeId'];
    $port   = $row['port'];
    $label  = $config['label'];
    $value  = $data[OrderField::Total->value];

Good:
    // Spatie Data for inbound JSON / config / raw arrays:
    $node = NodeRow::from($row);
    $nodeId = $node->nodeId;

    // Plain readonly value object for code you own:
    final readonly class NodeRow {
        public function __construct(
            public string $nodeId,
            public int $port,
        ) {}
    }

    // Enum-keyed lookups: hide ->value behind a typed matcher:
    OrderField::Total->matches($key);

Dictionary-shaped arrays (dynamic keys, homogeneous values) are fine —
annotate them with `@var array<string, T>` / `@param array<string, T>`
to opt out, where T is a CONCRETE value type. `array<string, mixed>`
and `array<string, array>` do NOT opt out: heterogeneous values mean
the values differ per key, which means the keys are a fixed known set
— that's a record wearing a dictionary's clothes. Name the value type
or build the DTO.

Exact array shapes are also accepted as typed when they describe
INBOUND data: `@param array{nodes: list<mixed>, edges: list<mixed>}
$graph` on a parameter, or a `@var array{...}` on data you received,
names a known shape and accesses into it are not flagged. Prefer a real
DTO where the structure lives longer than one normalisation step, but a
shape annotation on inbound data is an honest contract and counts. A
shape must declare at least one concrete value type — `array{name?:
mixed, type?: mixed}` is `array<string, mixed>` in shape clothing and
does NOT opt out.

A shape annotation does NOT bless a record you BUILD. Writing
`/** @var array{...} $payload */ $payload = ['key' => ...];` — slapping
a shape on an array literal you construct right here — is a dodge, not a
contract: you are hand-rolling a typed record as an array instead of a
DTO. The prophet DETECTS this: an `array{...}` annotation on a variable
that is assigned an array literal in the same statement is ignored, and
accesses into it are flagged as the same sin. Build the DTO / value
object and return THAT. (A genuine dictionary you build —
`@var array<string, Money> $prices = [...]` with a concrete homogeneous
value type — stays righteous; it is a real map, not a record.)

Wrapper helpers (`config()`, `Arr::get()`, `data_get()`, etc.) signal
"this is dynamic lookup" — but ONLY when the lookup actually is dynamic.
Calling `Arr::get($graph, 'nodes')` or `data_get($row, 'name')` with a
literal single-segment key is `$graph['nodes']` wearing a disguise, and
is flagged as the same sin. Wrapper calls stay exempt when the key is
dynamic (a variable or expression), a dotted deep path (`'nested.key'`
— the one-off deep-config case), or the target is annotated as a
genuine dictionary or exact shape.

STOP. READ THIS BEFORE FIXING ANYTHING.

Claude (and any other AI agent): you MUST NOT be clever and silence
these sins by blanket-replacing `$arr['key']` with `Arr::get($arr,
'key')`, `data_get(...)`, or any other wrapper helper. That defeats
the entire point of this rule. The sin is "structured data is being
passed around as an untyped array" — swapping the accessor syntax
does not fix that, it just hides the smell from the linter. The
prophet DETECTS this dodge: a wrapper call with a literal key on a
non-dictionary array is reported as the same sin. The only way out
is the DTO.

The DEFAULT fix is always: introduce a DTO (Spatie Data class) or a
`final readonly` value object at the boundary where the array enters
your code, and read typed properties from there on.

Wrapper helpers (`Arr::get`, `data_get`, `config`, etc.) are ONLY
acceptable when a DTO genuinely does not make sense, e.g.:
  - truly dynamic key lookup where the key is computed at runtime
  - dictionary-shaped data (homogeneous values, unknown key set)
  - one-off access to deeply nested config where modelling every
    branch as a DTO would be absurd

If you find yourself reaching for `Arr::get` on a fixed, known set
of string keys that describe a record — you are doing it wrong.
Build the DTO.

When a codebase index is available, the suggestion walks back through
caller methods up to `max_trace_depth` hops (default 10) to name the
originating method instead of the local one.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $pipe = new FindArrayStringIndexing();

        if ($this->codebaseIndex !== null && $this->config('cross_file_trace', true)) {
            $pipe = $pipe->withCodebaseIndex(
                $this->codebaseIndex,
                (int) $this->config('max_trace_depth', 10),
            );
        }

        return PhpPipeline::make($filePath, $content)
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractUseStatements::class)
            ->pipe($pipe)
            ->sinsFromMatches(
                fn ($match) => isset($match->groups['via'])
                    ? sprintf(
                        'Array string indexing via %s(%s, %s) — wrapper helpers do not absolve, wrap in a DTO',
                        $match->groups['via'],
                        $match->groups['var'],
                        $match->groups['key'],
                    )
                    : sprintf(
                        'Array string indexing on %s[%s] — wrap in a DTO',
                        $match->groups['var'],
                        $match->groups['key'],
                    ),
                fn ($match) => $match->groups['source_hint']
                    . '. Use a Spatie Data class for inbound arrays, or a `final readonly` value object for code you own.'
            )
            ->judge();
    }
}
