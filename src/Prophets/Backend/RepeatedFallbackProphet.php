<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractUseStatements;
use JesseGall\CodeCommandments\Support\Pipes\Php\FindRepeatedFallbacks;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

/**
 * Flag a computed null/falsy-fallback chain (`??`, `?:`, or a full-ternary
 * null/isset/empty check) that is written verbatim in two or more places, and
 * suggest hoisting it into one named static factory.
 *
 *
 *
 *
 *
 *
 * @method-generated-start
 * @method static minOccurrences(int $value)
 * @method static nullObjects(array $value)
 * @method-generated-end
 */
#[IntroducedIn('1.27.0')]
class RepeatedFallbackProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    private ?CodebaseIndex $codebaseIndex = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->codebaseIndex = $index;
    }

    public function description(): string
    {
        return 'Do not copy-paste a fallback chain — hoist a repeated `?? / ?:` into a named static factory';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A computed fallback written once is fine. Written in five places, it is
five copies of the same decision — and the day the fallback changes, you
get to find all five.

    // scattered across the codebase:
    $child = Pipeline::current()?->child() ?? Pipeline::make();

    // hoisted, named, one home:
    public static function currentChildOrMake(): self
    {
        return self::current()?->child() ?? self::make();
    }

    $child = Pipeline::currentChildOrMake();

THE RULE: when the SAME computed fallback chain repeats verbatim, give it
a name on the class it belongs to. The four surface forms collapse to the
same "value or fallback" shape and are all caught:

    Pipeline::current()?->child() ?? Pipeline::make()              // ??
    Pipeline::current()?->child() ?: Pipeline::make()              // ?:
    Pipeline::current()?->child() === null ? Pipeline::make()      // full ternary
        : Pipeline::current()?->child()
    is_null(Pipeline::current()) ? Pipeline::make() : Pipeline::current()

WHAT QUALIFIES — three gates, all required:

  - The VALUE side is a COMPUTED chain: it contains a static call or a
    free-function call (`Pipeline::current()`, `config(...)`). A bare
    `$user?->profile() ?? x` does NOT qualify — that is the null-object
    prophets' territory, not this one.
  - The fallback is a real value, not bare `null` (`X() ?? null` is a
    nullable getter, not a fallback worth a factory).
  - The identical expression appears at least twice across the codebase.

WHY NOT JUST LEAVE IT: the named factory is the one place the null
handling lives. Tests target it directly. A reader sees intent
(`currentChildOrMake`) instead of re-deriving it from `?? make()`. And
the next person can't write a SIXTH, subtly-different copy.

DEFERS TO THE NULL-OBJECT PROPHETS: if the fallback is a Null Object
(`?? new NullPipeline()` or a configured null-object class), this prophet
stays silent — returning the Null Object by DEFAULT from the source is the
better fix, and that belongs to PreferNullObjectDefaults.

This is a cross-file rule: it needs the whole scroll to count repeats, so
it is silent in single-`--file` mode. Not auto-fixable — extracting a
method and rewriting every call site is a refactor for a human to drive.

Claude (and any other AI agent): before pasting a `?? Class::make()` (or
its ternary equivalent) that already exists elsewhere, add a static
factory to the class and call that instead.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $pipe = (new FindRepeatedFallbacks)
            ->withMinOccurrences((int) $this->config('min_occurrences', 2))
            ->withNullObjectShortNames($this->nullObjectShortNames());

        if ($this->codebaseIndex !== null) {
            $pipe = $pipe->withCodebaseIndex($this->codebaseIndex);
        }

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
     * Short names of the configured null-object classes — the `null_objects`
     * map's values, mirroring PreferNullObjectDefaults so the two agree on
     * what a Null Object is.
     *
     * @return list<string>
     */
    private function nullObjectShortNames(): array
    {
        $map = $this->config('null_objects', []);

        if (! is_array($map)) {
            return [];
        }

        $names = [];

        foreach ($map as $nullClass) {
            if (is_string($nullClass) && $nullClass !== '') {
                $pos = strrpos($nullClass, '\\');
                $names[] = $pos === false ? $nullClass : substr($nullClass, $pos + 1);
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  array<string, string>  $groups
     */
    private function messageFor(array $groups): string
    {
        $where = $groups['others'] !== '' ? " (also at {$groups['others']})" : '';

        return sprintf(
            'Repeated fallback chain `%s` appears %s× across the codebase%s',
            $groups['expr'],
            $groups['count'],
            $where,
        );
    }

    /**
     * @param  array<string, string>  $groups
     */
    private function suggestionFor(array $groups): string
    {
        return sprintf(
            'Hoist it into %s so the fallback lives in one place, then call that everywhere.',
            $groups['suggestion'],
        );
    }
}
