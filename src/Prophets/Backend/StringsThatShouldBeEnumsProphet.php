<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractUseStatements;
use JesseGall\CodeCommandments\Support\Pipes\Php\FindStringsThatShouldBeEnums;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

/**
 * Flag raw string literals that are really enum cases in disguise.
 *
 * The detector recognises three patterns:
 *
 *   1. A named argument whose value is a string literal matching a case
 *      on an enum (imported in the file or — with a CodebaseIndex —
 *      defined anywhere in the project).
 *
 *   2. A `string`-typed parameter whose default literal matches a case
 *      on a name-matched enum.
 *
 *   3. A `string`-typed parameter whose call sites across the project
 *      use a small closed set of literals. This fires even when no
 *      enum exists yet — the highest-value moment to suggest creating
 *      one.
 */
#[IntroducedIn('1.6.0')]
class StringsThatShouldBeEnumsProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    private ?CodebaseIndex $codebaseIndex = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->codebaseIndex = $index;
    }

    public function description(): string
    {
        return 'Use enum cases instead of raw string literals for closed-set values';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Closed-set values — directions, statuses, kinds, modes, verbs — belong
in enums, not string literals. Stringly-typed values bypass static
analysis, IDE refactors, and exhaustive `match`.

This prophet flags three patterns:

1. Named argument matching an enum case.

   Bad:
       new Port(direction: 'input');
   Good:
       new Port(direction: PortDirection::Input);

   The enum may live anywhere in the project — when a codebase index is
   available, an unimported `PortDirection` still resolves and the fix
   is "add the `use` and replace the literal".

2. String-typed parameter default matching an enum case.

   Bad:
       public function __construct(public readonly string $status = 'running') {}
   Good:
       public function __construct(public readonly WorkflowRunStatus $status = WorkflowRunStatus::Running) {}

3. String-typed parameter whose call sites form a closed set.

   Bad:
       public function fanout(string $verb): void { /* … */ }

       $this->fanout($pub, 'publish');
       $this->fanout($pub, 'unpublish');
       $this->fanout($pub, 'publish');

   Good:
       public function fanout(MirroringAction $verb): void { /* … */ }

   The literal-frequency heuristic walks the codebase index to find
   what arguments are actually passed. When the distinct values are
   few (≤5), every value looks like a case name, and the param is
   used at multiple call sites, the prophet suggests an enum — even
   if one doesn't exist yet. When a name-matched enum DOES exist,
   the suggestion gets concrete.

Exceptions: values inside `toArray`, `jsonSerialize`, `render`, or
inside a `JsonResource`/`Resource`/`Response` class are left alone —
those are wire-format boundaries where the literal string is the
public contract.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $pipe = new FindStringsThatShouldBeEnums;

        if ($this->codebaseIndex !== null && $this->config('cross_file_trace', true)) {
            $pipe = $pipe->withCodebaseIndex($this->codebaseIndex);
        }

        return PhpPipeline::make($filePath, $content)
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractUseStatements::class)
            ->pipe($pipe)
            ->sinsFromMatches(
                fn ($match) => $this->messageFor($match),
                fn ($match) => $this->suggestionFor($match),
            )
            ->judge();
    }

    private function messageFor(\JesseGall\CodeCommandments\Support\Pipes\MatchResult $match): string
    {
        $groups = $match->groups;

        if ($match->name === 'param_closed_set') {
            return sprintf(
                "Stringly-typed parameter %s receives a closed set [%s] across its call sites — looks like an enum in disguise.",
                $groups['subject'],
                $groups['literals'],
            );
        }

        if ($match->name === 'param_closed_set_matched_enum') {
            return sprintf(
                "Stringly-typed parameter %s receives [%s] across its call sites — every value matches a case of %s.",
                $groups['subject'],
                $groups['literals'],
                $groups['enum_short'],
            );
        }

        $unimported = $groups['requires_import'] === '1';

        return sprintf(
            "Raw string literal '%s' for %s — %s case of %s. Replace with %s::%s%s.",
            $groups['value'],
            $groups['subject'],
            $unimported ? 'matches a' : 'looks like a',
            $groups['enum_short'],
            $groups['enum_short'],
            $groups['case'],
            $unimported ? " (add `use {$groups['enum_fqcn']};`)" : '',
        );
    }

    private function suggestionFor(\JesseGall\CodeCommandments\Support\Pipes\MatchResult $match): string
    {
        $groups = $match->groups;

        if ($match->name === 'param_closed_set') {
            return sprintf(
                'Define a `%s` enum with cases %s and retype %s to that enum.',
                $groups['enum_short'],
                $groups['literals'],
                $groups['subject'],
            );
        }

        if ($match->name === 'param_closed_set_matched_enum') {
            $importHint = $groups['requires_import'] === '1'
                ? sprintf(' Import `%s` and ', $groups['enum_fqcn'])
                : ' ';

            return sprintf(
                'Every call-site literal corresponds to a case of `%s`.%sretype %s to that enum.',
                $groups['enum_short'],
                $importHint,
                $groups['subject'],
            );
        }

        $base = 'Stringly-typed closed-set values bypass static analysis, IDE refactors, and exhaustive match. Use the enum case.';

        if (($groups['requires_import'] ?? '') === '1') {
            return $base . sprintf(' Add `use %s;` to this file.', $groups['enum_fqcn']);
        }

        return $base;
    }
}
