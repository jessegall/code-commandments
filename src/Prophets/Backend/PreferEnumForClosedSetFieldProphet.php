<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Suggest an enum for a `string`-typed field or parameter whose NAME reads as a
 * closed set — `direction`, `status`, `kind`, `mode`, `type`, … — even when no
 * literal and no enum exist yet.
 *
 * Its sibling, {@see StringsThatShouldBeEnumsProphet}, is literal-anchored: it
 * needs a string literal (a default, a named arg, a closed call-site set, a
 * match/switch/if) to fire. A bare `public string $direction` on a Data class
 * hydrated from an array offers no such anchor — yet the NAME alone is a strong
 * signal. This prophet fills that gap with a name heuristic, and (since that is
 * a softer signal than a literal) emits an advisory WARNING that suggests
 * CREATING a purpose-specific enum.
 */
#[IntroducedIn('1.91.0')]
class PreferEnumForClosedSetFieldProphet extends PhpCommandment
{
    /**
     * Field/param name endings that almost always denote a finite, closed set.
     * Matched case-insensitively at a word boundary (camelCase or snake_case),
     * so `sortDirection` and `node_type` match but `prototype` does not.
     *
     * @var list<string>
     */
    private const DEFAULT_NAMES = [
        'direction', 'status', 'state', 'kind', 'mode', 'type', 'level',
        'severity', 'visibility', 'role', 'format', 'strategy', 'operator',
        'phase', 'stage', 'category', 'variant', 'priority', 'alignment',
        'orientation', 'unit', 'period', 'scope', 'tier',
    ];

    /** Methods whose contents are a wire-format boundary — left alone. */
    private const WIRE_FORMAT_METHODS = ['toArray', 'jsonSerialize', 'render', 'toResponse', 'resolve'];

    public function description(): string
    {
        return 'Suggest an enum for a string field whose name denotes a closed set';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A `string`-typed field or parameter whose name denotes a closed set '
                . '(`$direction`, `$status`, `$kind`, `$mode`, `$type`, …) — a value '
                . 'with a known, finite set of cases that is currently stringly-typed.'
            )
            ->leaveWhen(
                'The value is genuinely open free text that merely shares the name '
                . '(a `$type` holding an arbitrary MIME string, a `$format` holding a '
                . 'user-supplied pattern), or it is a wire-format boundary.'
            )
            ->whenUnsure(
                'Ask "is the set of valid values finite and known?" If yes, it is an '
                . 'enum — create a purpose-specific one and type the field as it. If '
                . 'the value is genuinely open, leave it.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A closed-set value — a direction, status, kind, mode, type — belongs in an
enum, not a `string`. Stringly-typed closed sets bypass static analysis, IDE
refactors, and exhaustive `match`; every consumer re-validates by hand.

The companion rule (StringsThatShouldBeEnums) needs a literal to fire — a
default, a named argument, a closed set of call-site values, a match/switch/if.
A field hydrated from an array (a Spatie Data property, say) has no such anchor:

    class NodeSocketData extends Data
    {
        public function __construct(
            public string $direction,   // ← no default, no literal — invisible to the literal rule
        ) {}
    }

But the NAME is signal enough. This rule flags a `string` field/param whose name
ends in a closed-set noun (at a camelCase or snake_case boundary, so `prototype`
is not mistaken for `…type`) and suggests creating a purpose-specific enum:

    enum SocketDirection: string { case Input = 'input'; case Output = 'output'; }

    public SocketDirection $direction,

It is an ADVISORY warning, not a sin: the name is a softer signal than a
literal, so review each one. A genuinely open `string` that merely shares the
name (a free-text `$format`) should be left — absolve it with that reason.

WHAT FIRES — a `string` / `?string` typed property or parameter whose name
matches the configured closed-set list at a word boundary.

WHAT DOES NOT — non-string types, a name not in the list, a value inside a
wire-format method (`toArray`/`jsonSerialize`/`render`/…), or — when the list
is configured empty — nothing.

Configure via:

    Backend\PreferEnumForClosedSetFieldProphet::class => [
        'names' => ['direction', 'status', 'kind', 'mode', 'type', /* … */],
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $names = $this->closedSetNames();

        if ($names === []) {
            return $this->righteous();
        }

        $visitor = new class($names, fn (string $n, array $list): ?string => $this->matchedNoun($n, $list)) extends NodeVisitorAbstract
        {
            /** @var list<array{line: int, name: string, noun: string, kind: string}> */
            public array $hits = [];

            /** @var list<string> */
            private array $functionStack = [];

            /**
             * @param  list<string>  $names
             * @param  callable(string, list<string>): ?string  $match
             */
            public function __construct(private array $names, private $match) {}

            public function enterNode(Node $node): null
            {
                if ($node instanceof Node\FunctionLike) {
                    $name = $node instanceof Node\Stmt\ClassMethod ? $node->name->toString() : '';
                    $this->functionStack[] = $name;
                }

                if ($this->inWireFormatMethod()) {
                    return null;
                }

                // Only PROMOTED constructor params (i.e. declared fields). Plain
                // method parameters too often carry class-strings / type names
                // (`findNodes(string $nodeType)`) — the value is a data FIELD, not
                // every transient string argument.
                if ($node instanceof Node\Param
                    && $node->flags !== 0
                    && $node->var instanceof Node\Expr\Variable
                    && is_string($node->var->name)) {
                    $this->consider($node->type, $node->var->name, $node->getStartLine(), 'property');
                }

                if ($node instanceof Node\Stmt\Property) {
                    foreach ($node->props as $prop) {
                        $this->consider($node->type, $prop->name->toString(), $prop->getStartLine(), 'property');
                    }
                }

                return null;
            }

            public function leaveNode(Node $node): null
            {
                if ($node instanceof Node\FunctionLike) {
                    array_pop($this->functionStack);
                }

                return null;
            }

            private function consider(?Node $type, string $name, int $line, string $kind): void
            {
                if (! $this->isStringType($type)) {
                    return;
                }

                $noun = ($this->match)($name, $this->names);

                if ($noun !== null) {
                    $this->hits[] = ['line' => $line, 'name' => $name, 'noun' => $noun, 'kind' => $kind];
                }
            }

            private function isStringType(?Node $type): bool
            {
                if ($type instanceof Node\NullableType) {
                    $type = $type->type;
                }

                return $type instanceof Node\Identifier && strtolower($type->toString()) === 'string';
            }

            private function inWireFormatMethod(): bool
            {
                foreach ($this->functionStack as $name) {
                    if (in_array($name, PreferEnumForClosedSetFieldProphet::wireFormatMethods(), true)) {
                        return true;
                    }
                }

                return false;
            }
        };

        $this->traverse($ast, $visitor);

        $warnings = [];

        foreach ($visitor->hits as $hit) {
            $enum = ucfirst($hit['noun']);

            $warnings[] = $this->warningAt(
                $hit['line'],
                sprintf(
                    'The string %s `$%s` reads like a closed set (a %s). Stringly-typed closed sets bypass static analysis, IDE refactors, and exhaustive `match`. If its values are a known finite set, create a purpose-specific enum (e.g. `enum %s: string { case … }`) and type %s as it. If it is genuinely open free text, leave it.',
                    $hit['kind'],
                    $hit['name'],
                    $hit['noun'],
                    $enum,
                    "`\${$hit['name']}`",
                ),
                "closed-set-field:{$hit['name']}",
            );
        }

        if ($warnings === []) {
            return $this->righteous();
        }

        return Judgment::withWarnings($warnings);
    }

    /**
     * @return list<string>
     */
    public static function wireFormatMethods(): array
    {
        return self::WIRE_FORMAT_METHODS;
    }

    /**
     * The closed-set noun a name ends in, at a camelCase or snake_case boundary
     * — or null. `sortDirection` → `direction`, `node_type` → `type`,
     * `prototype` → null.
     *
     * @param  list<string>  $names
     */
    private function matchedNoun(string $name, array $names): ?string
    {
        $lower = strtolower($name);

        foreach ($names as $noun) {
            if ($lower === $noun) {
                return $noun;
            }

            if (! str_ends_with($lower, $noun)) {
                continue;
            }

            $start = strlen($name) - strlen($noun);
            $boundaryChar = $name[$start];
            $previous = $start > 0 ? $name[$start - 1] : '';

            // camelCase boundary (suffix capitalised) or snake_case (`_` before).
            if (ctype_upper($boundaryChar) || $previous === '_') {
                return $noun;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function closedSetNames(): array
    {
        $configured = $this->config('names', self::DEFAULT_NAMES);

        if (! is_array($configured)) {
            return self::DEFAULT_NAMES;
        }

        return array_values(array_map(
            static fn (string $n): string => strtolower($n),
            array_filter($configured, 'is_string'),
        ));
    }
}
