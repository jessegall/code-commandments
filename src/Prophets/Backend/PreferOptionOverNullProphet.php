<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractUseStatements;
use JesseGall\CodeCommandments\Support\Pipes\Php\FindNullableValueReturns;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

/**
 * Flag methods that decide between a value and nothing by returning null,
 * judged by the BODY (an explicit `return null;` next to value returns),
 * not the signature. Suggests an Option wrapper, or a configured Null
 * Object when one exists for the return type.
 */
#[IntroducedIn('1.19.0')]
class PreferOptionOverNullProphet extends PhpCommandment
{
    private const DEFAULT_EXCLUDED_METHODS = ['try*', '__*'];

    public function description(): string
    {
        return 'Do not return null from decision methods — return an Option or a Null Object';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A method that sometimes returns a value and sometimes returns null
pushes a hidden branch onto every caller. Each call site grows a null
check, a `?->`, or a `??` — and forgetting one is a TypeError waiting
in production. Make the empty case a real type.

This prophet reads the BODY, not the signature: it fires when a method
contains an explicit `return null;` (or `return $x ? $val : null;`)
alongside at least one value return. Getters returning a nullable
property and passthroughs of someone else's nullable do not fire —
they carry data; the flagged methods DECIDE nothingness.

Bad:
    private static function triggerSourceFor(Node $node, Graph $graph): PortRef|null
    {
        foreach ($graph->edges as $edge) {
            if ($edge->to->nodeId === $node->id) {
                return $edge->from;
            }
        }

        return null;
    }

    // Every caller now does:
    $source = self::triggerSourceFor($node, $graph);
    if ($source === null) { ... }   // or ?->, or ??

Good — Option makes the empty case explicit and impossible to forget:
    private static function triggerSourceFor(Node $node, Graph $graph): Option
    {
        foreach ($graph->edges as $edge) {
            if ($edge->to->nodeId === $node->id) {
                return Option::some($edge->from);
            }
        }

        return Option::none();
    }

    // Callers say what they mean:
    $label = self::triggerSourceFor($node, $graph)
        ->map(fn (PortRef $ref) => $ref->nodeId)
        ->getOr('unconnected');

Also good — a Null Object, when the type has behavior the caller
invokes rather than branches on. Configure these per type in the
`null_objects` map and return the sentinel instead of null:

    return new NullPortRef();   // ->isEmpty() === true, methods no-op

If the project has no Option type yet, create one and point the
`option_class` config at it:

    /**
     * @template T
     */
    final readonly class Option
    {
        private function __construct(
            private mixed $value,
            private bool $hasValue,
        ) {}

        /**
         * @template TValue
         * @param  TValue  $value
         * @return self<TValue>
         */
        public static function some(mixed $value): self
        {
            return new self($value, true);
        }

        /**
         * @return self<never>
         */
        public static function none(): self
        {
            return new self(null, false);
        }

        public function hasValue(): bool
        {
            return $this->hasValue;
        }

        /**
         * @return T
         */
        public function getOrThrow(): mixed
        {
            if (! $this->hasValue) {
                throw new \LogicException('Option is empty');
            }

            return $this->value;
        }

        /**
         * @template TDefault
         * @param  TDefault  $default
         * @return T|TDefault
         */
        public function getOr(mixed $default): mixed
        {
            return $this->hasValue ? $this->value : $default;
        }

        /**
         * @template TOut
         * @param  callable(T): TOut  $map
         * @return self<TOut>
         */
        public function map(callable $map): self
        {
            return $this->hasValue ? self::some($map($this->value)) : self::none();
        }

        /**
         * @param  callable(T): void  $callback
         */
        public function each(callable $callback): void
        {
            if ($this->hasValue) {
                $callback($this->value);
            }
        }
    }

USAGE RULES — read before refactoring:

  - `if ($option->hasValue()) { $x = $option->getOrThrow(); }` is a
    null check with extra steps. Prefer map()/getOr()/each(); reach
    for getOrThrow() when emptiness is a bug, and hasValue() only for
    genuine branching where both paths do real work.
  - If the empty case is truly exceptional (caller can never proceed),
    don't wrap — throw a domain exception instead of returning null.
  - Collections never need Option: return an empty collection.
  - Do NOT dodge this rule by renaming the method to try*(), hiding
    null in `?? null` chains, or returning Option and immediately
    calling getOrThrow() at every call site. The goal is fewer hidden
    branches, not new syntax for the same branches.

Methods named try* and magic methods (__get etc.) are excluded by
convention; #[Override] methods are excluded because the signature
isn't theirs to change. Configure via:

    Backend\PreferOptionOverNullProphet::class => [
        'option_class' => App\Support\Option::class,
        'null_objects' => [
            App\Workflow\PortRef::class => App\Workflow\NullPortRef::class,
        ],
        'exclude_methods' => ['try*', '__*'],
        'severity' => 'warning',   // or 'sin' to block commits
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $pipe = (new FindNullableValueReturns)
            ->withExcludedMethods($this->resolveExcludedMethods());

        return PhpPipeline::make($filePath, $content)
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractUseStatements::class)
            ->pipe($pipe)
            ->partitionMatches(fn (MatchResult $match) => $this->translate($match))
            ->judge();
    }

    private function translate(MatchResult $match): Sin|Warning
    {
        $message = $this->messageFor($match);
        $suggestion = $this->suggestionFor($match);

        if ($this->config('severity', 'warning') === 'sin') {
            return $this->sinAt($match->line, $message, $match->content, $suggestion);
        }

        return $this->warningAt($match->line, $message . ' ' . $suggestion, $match->content);
    }

    private function messageFor(MatchResult $match): string
    {
        $groups = $match->groups;
        $type = $groups['type_name'] !== '' ? $groups['type_name'] . ' | null' : 'a value | null';

        return sprintf(
            '%s returns %s — the body decides nothingness (%s `return null`, %s value return%s).',
            $groups['method'],
            $type,
            $groups['null_count'],
            $groups['value_count'],
            $groups['value_count'] === '1' ? '' : 's',
        );
    }

    private function suggestionFor(MatchResult $match): string
    {
        $groups = $match->groups;
        $nullObject = $this->nullObjectFor($groups['type_fqcn'], $groups['type_name']);

        if ($nullObject !== null) {
            return sprintf(
                'Return `new %s` (configured null object for %s) instead of null, and let callers check ->isEmpty() or rely on no-op behavior.',
                $this->shortClassName($nullObject),
                $groups['type_name'],
            );
        }

        $optionClass = $this->config('option_class');

        if (is_string($optionClass) && $optionClass !== '') {
            $short = $this->shortClassName($optionClass);

            return sprintf(
                'Wrap in %s: return %s::some($value) / %s::none(); callers use ->map()/->getOr()/->getOrThrow() instead of null checks.',
                $optionClass,
                $short,
                $short,
            );
        }

        return 'Introduce an Option type (the scripture contains a complete implementation), set `option_class` in this prophet\'s config, and return Option::some($value) / Option::none().';
    }

    private function nullObjectFor(string $fqcn, string $typeName): ?string
    {
        $map = $this->config('null_objects', []);

        if (! is_array($map) || $map === []) {
            return null;
        }

        foreach ($map as $key => $nullObject) {
            if ($fqcn !== '' && strcasecmp($key, $fqcn) === 0) {
                return $nullObject;
            }

            if ($typeName !== '' && strcasecmp($this->shortClassName($key), $typeName) === 0) {
                return $nullObject;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function resolveExcludedMethods(): array
    {
        $patterns = $this->config('exclude_methods', self::DEFAULT_EXCLUDED_METHODS);

        return is_array($patterns) ? array_values($patterns) : self::DEFAULT_EXCLUDED_METHODS;
    }

    private function shortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }
}
