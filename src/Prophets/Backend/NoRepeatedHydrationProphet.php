<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Php\FindRepeatedHydration;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

/**
 * Flag the same field hydrated to the same type more than once via
 * `::from()`. A field that keeps being hydrated should simply BE that type.
 *
 *
 *
 *
 * @method-generated-start
 * @method static methods(array $value)
 * @method static minOccurrences(int $value)
 * @method-generated-end
 */
#[IntroducedIn('1.37.0')]
class NoRepeatedHydrationProphet extends PhpCommandment
{
    private const DEFAULT_MIN_OCCURRENCES = 2;

    public function description(): string
    {
        return 'Do not re-hydrate the same field with ::from() — declare it as the type so it hydrates once';
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'The same field is hydrated to the same type in two or more places '
                . '(e.g. StepExtrasData::from($step->extras) repeated across methods) '
                . 'AND you own the class that declares the field — typing it once '
                . 'removes the repetition and the risk of the hydrations drifting apart.'
            )
            ->leaveWhen(
                'The field genuinely arrives as a raw array at an external boundary you '
                . 'cannot type, or the repeated ::from() calls really operate on different '
                . 'shapes that happen to share a property name.'
            )
            ->whenUnsure(
                'Prefer typing the field. When the same ::from() on the same field repeats, '
                . 'declaring the type is almost always the right call.'
            );
    }

    protected function defaultTier(): Tier
    {
        return Tier::Structural;
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Hydrating the SAME field with `::from()` in more than one place is a typed
property waiting to happen. Each call site re-derives a value that should
already carry its type — and the moment two call sites hydrate it slightly
differently, you have a bug that a single typed field would have made
impossible.

This prophet fires when one `<Class>::from($x->field)` shape (same target
type, same property name) appears at least `min_occurrences` (default 2)
times in a file. The base variable is ignored, so the same field reached
through different locals still groups.

Bad — `$step->extras` re-hydrated everywhere:
    private static function runStateAt(array $steps): array
    {
        $stashed = StepExtrasData::from($steps[$i]->extras)->context;   // (1)
        // ...
    }

    private static function classifyNode(array $steps): array
    {
        $reason = StepExtrasData::from($skipped->extras)->reason;       // (2)
        // ...
    }

    private static function failureDetail(StepEntry $step): ?string
    {
        $extras = StepExtrasData::from($step->extras);                  // (3)
        // ...
    }

Good — type the field once, then just read it:
    final class StepEntry extends Data
    {
        public function __construct(
            // ...
            public readonly StepExtrasData $extras,   // hydrated once, by ::from()
        ) {}
    }

    $step->extras->context;   // no ::from(), no divergence
    $step->extras->reason;
    $step->extras;

If the field arrives as a raw array, that is exactly what a typed property,
`#[DataCollectionOf(...)]`, or a Cast is for — let Spatie Data hydrate it at
the boundary instead of every consumer doing it by hand.

This rule also catches repeated enum/value-object hydration:
`Status::from($row->status)` in many places means `$row->status` should be
typed `Status`.

Configure via:

    Backend\NoRepeatedHydrationProphet::class => [
        'min_occurrences' => 2,       // how many repeats before flagging
        'methods' => ['from'],        // static creators to watch
        'severity' => 'warning',      // or 'sin' to block commits
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $pipe = (new FindRepeatedHydration)
            ->withMethods($this->resolveMethods())
            ->withMinOccurrences($this->resolveMinOccurrences());

        return PhpPipeline::make($filePath, $content)
            ->pipe(ParsePhpAst::class)
            ->pipe($pipe)
            ->partitionMatches($this->translate(...))
            ->judge();
    }

    private function translate(MatchResult $match): Sin|Warning
    {
        $message = $this->messageFor($match);
        $suggestion = $this->suggestionFor($match);
        $symbol = $match->groups['target'] . '::' . $match->groups['property'];

        if ($this->config('severity', 'warning') === 'sin') {
            return $this->sinAt($match->line, $message, $match->content, $suggestion, $symbol);
        }

        return $this->warningAt($match->line, $message . ' ' . $suggestion, $match->content, $symbol);
    }

    private function messageFor(MatchResult $match): string
    {
        $groups = $match->groups;

        return sprintf(
            '%s::from($…->%s) hydrates the same field %d times — the body keeps re-deriving a value that should carry its type.',
            $groups['target'],
            $groups['property'],
            (int) $groups['count'],
        );
    }

    private function suggestionFor(MatchResult $match): string
    {
        $groups = $match->groups;

        return sprintf(
            'Declare `%s` as %s on its owning class (a typed Data property, #[DataCollectionOf], or a Cast), then read ->%s directly instead of calling %s::from() again.',
            $groups['property'],
            $groups['target'],
            $groups['property'],
            $groups['target'],
        );
    }

    /**
     * @return list<string>
     */
    private function resolveMethods(): array
    {
        $methods = $this->config('methods', ['from']);

        return is_array($methods) && $methods !== [] ? array_values($methods) : ['from'];
    }

    private function resolveMinOccurrences(): int
    {
        $value = $this->config('min_occurrences', self::DEFAULT_MIN_OCCURRENCES);

        return is_numeric($value) ? max(2, (int) $value) : self::DEFAULT_MIN_OCCURRENCES;
    }
}
