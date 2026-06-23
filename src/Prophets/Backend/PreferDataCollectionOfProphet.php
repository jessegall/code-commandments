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
use JesseGall\CodeCommandments\Support\PackageDetector;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\Php\FindManualDataCollection;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

/**
 * Flag a collection of Data objects hydrated by hand — a foreach appending
 * `SomeData::from($row)`, or `array_map(fn ($r) => SomeData::from($r), ...)`
 * — where Spatie Data's `#[DataCollectionOf]` / `::collect()` would do it
 * declaratively.
 */
#[IntroducedIn('1.38.0')]
class PreferDataCollectionOfProphet extends PhpCommandment
{
    public function supported(): bool
    {
        return PackageDetector::hasSpatieData();
    }

    public function description(): string
    {
        return 'Do not hand-roll a Data collection with ::from() in a loop — use #[DataCollectionOf] / ::collect()';
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'You are building an array/collection by calling SomeData::from() on each '
                . 'element of a source list (a foreach append or an array_map). Spatie can '
                . 'hydrate the whole collection in one declarative step.'
            )
            ->leaveWhen(
                'The loop does real per-element work beyond hydration — filtering, branching, '
                . 'accumulating, or transforming — or the source is not a uniform list of one '
                . 'Data type.'
            )
            ->whenUnsure(
                'If the loop body is essentially just SomeData::from() per element, prefer '
                . '#[DataCollectionOf] on the property (or SomeData::collect($source)).'
            );
    }

    protected function defaultTier(): Tier
    {
        return Tier::Structural;
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Building a collection of Data objects with a hand `::from()` loop is
reimplementing what Spatie Data already does declaratively. A foreach that
appends `SomeData::from($row)`, or an `array_map` whose callback is
`SomeData::from`, is one element of boilerplate at a time.

Bad — manual loop:
    $hydrated = [];

    foreach ($outcome->steps as $row) {
        $hydrated[] = StepEntry::from($row);
    }

    return $hydrated;

Bad — manual array_map:
    return array_map(static fn (array $entry) => Field::from($entry), $entries);

Good — let the framework hydrate the collection:
    // On the property:
    #[DataCollectionOf(StepEntry::class)]
    public readonly DataCollection $steps;

    // Or, ad hoc:
    return StepEntry::collect($outcome->steps);
    return Field::collect($entries);

Only a straight `::from` is flagged. A custom mapper —
`array_map(fn ($p) => NodePortData::forInputPort($p), $ports)` — is genuine
per-element transformation, not collection hydration, and is left alone.
(Use a `for*` prefix, never `from*`: the `from` prefix is reserved for
Spatie's magic ::from() — see NoExternalDataFrom.)

Configure via:

    Backend\PreferDataCollectionOfProphet::class => [
        'methods' => ['from'],     // static creators that count as hydration
        'severity' => 'warning',   // or 'sin' to block commits
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $pipe = (new FindManualDataCollection)->withMethods($this->resolveMethods());

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
        $symbol = $match->groups['target'] . '::from:' . $match->groups['form'];

        if ($this->config('severity', 'warning') === 'sin') {
            return $this->sinAt($match->line, $message, $match->content, $suggestion, $symbol);
        }

        return $this->warningAt($match->line, $message . ' ' . $suggestion, $match->content, $symbol);
    }

    private function messageFor(MatchResult $match): string
    {
        $groups = $match->groups;
        $shape = $groups['form'] === 'array_map' ? 'an array_map' : 'a foreach loop';

        return sprintf(
            '%s::from() hydrates a collection by hand in %s — Spatie Data can hydrate the whole collection declaratively.',
            $groups['target'],
            $shape,
        );
    }

    private function suggestionFor(MatchResult $match): string
    {
        $target = $match->groups['target'];

        return sprintf(
            'Type the property as DataCollection with #[DataCollectionOf(%s::class)], or call %s::collect($source) instead of the loop.',
            $target,
            $target,
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
}
