<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\CallGraph\OptionConsumptionResolver;
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
class PreferOptionOverNullProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    private const DEFAULT_EXCLUDED_METHODS = ['try*', '__*'];

    /**
     * Framework hook method names whose signature is the framework's, not the
     * author's — exempt when the class has a vendor ancestor (#94). Command/Job
     * `handle`, Spatie Data `morph`, Eloquent/provider `boot`/`booted`/`register`/
     * `casts`.
     */
    private const DEFAULT_FRAMEWORK_METHODS = ['handle', 'morph', 'boot', 'booted', 'register', 'casts'];

    private const DEFAULT_MIN_CALLERS = 2;

    private ?CodebaseIndex $index = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    protected function defaultTier(): Tier
    {
        return Tier::Structural;
    }

    public function description(): string
    {
        return 'Do not return null from decision methods — return an Option or a Null Object';
    }

    /**
     * Never flag the configured Option primitive itself — it is the recommended
     * fix, and it legitimately deals in null/absence internally. FQCN-matched,
     * so a domain class sharing the short name is still judged.
     *
     * @return list<class-string>
     */
    public function exemptClasses(): array
    {
        $class = ltrim((string) ($this->config('option_class') ?: 'App\\Support\\Option'), '\\');

        return $class === '' ? [] : [$class];
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'The method has several callers — each one otherwise grows its own '
                . 'null check (=== null, ?->, or ??), and forgetting one is a '
                . 'TypeError in production. The more call sites, the stronger the case.'
            )
            ->leaveWhen(
                'There are only one or two callers, or the empty case is local and '
                . 'obvious right where it is handled. Wrapping a single nearby check '
                . 'in an Option buys nothing.'
            )
            ->whenUnsure(
                'Leave it. This is a refactor toward fewer hidden branches, not a '
                . 'mandate to wrap every nullable return in new syntax.'
            );
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
        'min_callers' => 2,        // suppress when the index resolves fewer
                                   // than this many call sites (the refactor
                                   // only pays off with several consumers)
        'severity' => 'warning',   // or 'sin' to block commits
    ],

WHEN THIS FIRES (applicability): the value of an Option grows with the
number of callers — every call site otherwise repeats the same null check.
When the cross-file index can resolve how many call sites a method has and
that count is below `min_callers`, this prophet stays SILENT: a single
nearby null check is not worth an Option. Zero resolved callers is treated
as "unknown" (a public method may have callers the index can't see) and is
NOT suppressed. Single-file runs have no index, so nothing is suppressed.
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
            ->partitionMatches($this->translate(...))
            ->judge();
    }

    private function translate(MatchResult $match): Sin|Warning|null
    {
        // #94: a framework-locked signature — a recognized framework hook
        // (handle/morph/…) on a class with a VENDOR ancestor — isn't ours to
        // change to Option; the framework calls it and dictates the return type.
        if ($this->isFrameworkLocked($match)) {
            return null;
        }

        $callers = $this->callerInfoFor($match);

        // Measure & suppress: when the index can resolve how many call sites
        // depend on this method and that number is below the threshold, the
        // refactor isn't worth the ceremony — stay silent.
        if ($callers['known'] && $callers['count'] < $this->minCallers()) {
            return null;
        }

        $message = $this->messageFor($match, $callers);
        $suggestion = $this->suggestionFor($match);

        $symbol = $match->groups['method'] ?? null;

        if ($this->config('severity', 'warning') === 'sin') {
            return $this->sinAt($match->line, $message, $match->content, $suggestion, $symbol);
        }

        return $this->warningAt($match->line, $message . ' ' . $suggestion, $match->content, $symbol);
    }

    /**
     * Resolve how many call sites the index can attribute to this method.
     *
     * Zero resolved callers is treated as UNKNOWN, not "unused": a public
     * method may have callers the index can't see (out of scroll, dynamic
     * dispatch). We only suppress when we positively know the count is low.
     *
     * @return array{known: bool, count: int}
     */
    private function callerInfoFor(MatchResult $match): array
    {
        if ($this->index === null) {
            return ['known' => false, 'count' => 0];
        }

        $fqcn = $match->groups['class_fqcn'] ?? '';
        $method = $match->groups['method_name'] ?? '';

        if ($fqcn === '' || $method === '') {
            return ['known' => false, 'count' => 0];
        }

        // ZERO resolved callers is UNKNOWN, not "uncalled": the index may simply be
        // unable to attribute the calls (enum-method / dynamic dispatch). Stay silent
        // about the count → emit, as before.
        if ($this->index->callersOf($fqcn, $method) === []) {
            return ['known' => false, 'count' => 0];
        }

        // With RESOLVED callers, don't just COUNT them — classify how they CONSUME the
        // nullable. Option earns its place only where callers MANUALLY BRANCH on
        // absence (`=== null` / `if (! $x)`). A boundary method whose resolved callers
        // only PASS the value on or read it nullsafe gains nothing from forced Option
        // handling — that is positive evidence, so the "count" the gate weighs against
        // min_callers is the number of null-BRANCHING sites.
        $consumptions = (new OptionConsumptionResolver)->consumptions($fqcn, $method, $this->index);
        $branching = count(array_filter($consumptions, static fn (string $kind): bool => $kind === 'nullcheck'));

        return ['known' => true, 'count' => $branching];
    }

    private function minCallers(): int
    {
        $value = $this->config('min_callers', self::DEFAULT_MIN_CALLERS);

        return is_numeric($value) ? max(1, (int) $value) : self::DEFAULT_MIN_CALLERS;
    }

    /**
     * @param  array{known: bool, count: int}  $callers
     */
    private function messageFor(MatchResult $match, array $callers): string
    {
        $groups = $match->groups;
        $type = $groups['type_name'] !== '' ? $groups['type_name'] . ' | null' : 'a value | null';

        $callerClause = $callers['known']
            ? sprintf(
                ' %d call site%s carr%s this null check.',
                $callers['count'],
                $callers['count'] === 1 ? '' : 's',
                $callers['count'] === 1 ? 'ies' : 'y',
            )
            : '';

        return sprintf(
            '%s returns %s — the body decides nothingness (%s `return null`, %s value return%s).%s',
            $groups['method'],
            $type,
            $groups['null_count'],
            $groups['value_count'],
            $groups['value_count'] === '1' ? '' : 's',
            $callerClause,
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

    /**
     * #94: whether the flagged method's signature is dictated by a framework, so
     * returning Option would break the contract. True when its name is a known
     * framework hook (configurable `framework_methods`) AND the declaring class
     * has a VENDOR ancestor — a parent in the `extends` chain that is NOT a
     * project class (so its signature is inherited from outside the codebase:
     * `Illuminate\Console\Command::handle`, a Spatie Data `morph`, …). Without an
     * index the ancestry can't be checked, so the hook name alone exempts it
     * (conservative — these names are framework-specific).
     */
    private function isFrameworkLocked(MatchResult $match): bool
    {
        $name = $match->groups['method_name'] ?? '';

        if (! in_array($name, $this->frameworkMethods(), true)) {
            return false;
        }

        $classFqcn = ltrim((string) ($match->groups['class_fqcn'] ?? ''), '\\');

        if ($this->index === null || $classFqcn === '') {
            return true;
        }

        return $this->hasVendorAncestor($classFqcn);
    }

    /**
     * Whether $classFqcn's parent chain leads to a class the index does NOT know
     * — i.e. a vendor/framework base outside the scanned codebase.
     */
    private function hasVendorAncestor(string $classFqcn): bool
    {
        $summary = $this->index?->classByFqcn($classFqcn);
        $depth = 0;

        while ($summary !== null && $summary->parent !== null && $depth++ < 16) {
            $parent = ltrim($summary->parent, '\\');
            $parentSummary = $this->index?->classByFqcn($parent);

            if ($parentSummary === null) {
                return true; // parent declared but not in the index → vendor base
            }

            $summary = $parentSummary;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function frameworkMethods(): array
    {
        $methods = $this->config('framework_methods', self::DEFAULT_FRAMEWORK_METHODS);

        return is_array($methods) && $methods !== [] ? array_values(array_map('strval', $methods)) : self::DEFAULT_FRAMEWORK_METHODS;
    }
}
