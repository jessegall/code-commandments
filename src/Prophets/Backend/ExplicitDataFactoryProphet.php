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
use JesseGall\CodeCommandments\Support\Pipes\Php\FindImplicitDataFrom;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

/**
 * Keep Spatie Data construction explicit: `from()` takes an array, never the
 * magic object dispatch; object→Data mapping lives in named fromX() factories.
 */
#[IntroducedIn('1.40.0')]
class ExplicitDataFactoryProphet extends PhpCommandment
{
    public function supported(): bool
    {
        return PackageDetector::hasSpatieData();
    }

    public function description(): string
    {
        return 'Keep Data construction explicit — from() takes an array; map objects in named fromX() factories';
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A Data object is built with from() on a non-array (a model/object), '
                . 'with a ->toArray() bypass at a call site, or with new self()/new static() '
                . 'in a factory — all of which hide the mapping in magic or boilerplate.'
            )
            ->leaveWhen(
                'The construction is genuinely array-in (from([...]) / from($arrayParam)), or '
                . 'the type cannot be resolved, in which case this prophet stays silent.'
            )
            ->whenUnsure(
                'Add an explicit `fromX(Type $x): static` factory that does '
                . 'static::from($x->toArray()); call that from the outside.'
            );
    }

    protected function defaultTier(): Tier
    {
        return Tier::Structural;
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Spatie Data's `::from()` is magic: it dispatches by argument type to fromX()
methods, and falls back to calling toArray()/all() on models, requests and
arrayables. Convenient, but at a call site you cannot see which path it
takes. Keep it explicit: `from()` takes an ARRAY; everything else goes
through a named factory.

Bad — magic object dispatch:
    $data = SongData::from($song);          // model → magic / toArray fallback
    $data = SongData::from($request);       // request → all()
    $data = SongData::from($this->song);    // object property

Bad — toArray bypass at a call site (the conversion belongs in a factory):
    $data = SongData::from($song->toArray());

Bad — hand construction in a factory:
    public static function fromSong(Song $song): self
    {
        return new self(title: $song->title, artist: $song->artist);
    }

Good — explicit factory, array hydration encapsulated inside the class:
    public static function fromSong(Song $song): self
    {
        return static::from($song->toArray());
        // or: return static::from(['title' => $song->title, ...]);
    }

    // call sites stay explicit:
    $data = SongData::fromSong($song);
    $data = SongData::from(['title' => 'x', 'artist' => 'y']);   // array → fine

Enums are unaffected: `Status::from($row->status)` passes a scalar, never an
object, so the object check never touches it.

Argument types are resolved from the AST (parameter hints, $this, property
types, new, ->toArray()); when a type cannot be resolved the prophet stays
silent rather than guess.

Pairs with the generated FromArrayOnly trait, which enforces the same rule at
runtime (assert) for the cases static analysis cannot see.

Configure via:

    Backend\ExplicitDataFactoryProphet::class => [
        'data_suffixes' => ['Data'],   // base-class suffixes that mark a Data class
        'severity' => 'warning',       // or 'sin' to block commits
    ],
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $pipe = (new FindImplicitDataFrom)->withDataSuffixes($this->resolveSuffixes());

        return PhpPipeline::make($filePath, $content)
            ->pipe(ParsePhpAst::class)
            ->pipe($pipe)
            ->partitionMatches(fn (MatchResult $match) => $this->translate($match))
            ->judge();
    }

    private function translate(MatchResult $match): Sin|Warning
    {
        $message = $this->messageFor($match);
        $suggestion = $this->suggestionFor($match);
        $symbol = $match->groups['target'] . ':' . $match->groups['kind'];

        if ($this->config('severity', 'warning') === 'sin') {
            return $this->sinAt($match->line, $message, $match->content, $suggestion, $symbol);
        }

        return $this->warningAt($match->line, $message . ' ' . $suggestion, $match->content, $symbol);
    }

    private function messageFor(MatchResult $match): string
    {
        $target = $match->groups['target'];

        return match ($match->groups['kind']) {
            'toarray_outside' => sprintf(
                '%s::from($x->toArray()) converts an object to an array at the call site — that bypass belongs in a factory.',
                $target,
            ),
            'new_in_factory' => sprintf(
                'new %s() constructs the Data object by hand in a factory — hydrate through static::from(array) instead.',
                $target,
            ),
            default => sprintf(
                '%s::from() is given a non-array (object) — that is the magic dispatch; from() should take an array.',
                $target,
            ),
        };
    }

    private function suggestionFor(MatchResult $match): string
    {
        $target = $match->groups['target'];

        return sprintf(
            'Add an explicit `%s::fromX(Type $x): static` factory that does static::from($x->toArray()), and call that instead.',
            $target,
        );
    }

    /**
     * @return list<string>
     */
    private function resolveSuffixes(): array
    {
        $suffixes = $this->config('data_suffixes', ['Data']);

        return is_array($suffixes) && $suffixes !== [] ? array_values($suffixes) : ['Data'];
    }
}
