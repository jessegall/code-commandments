<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractUseStatements;
use JesseGall\CodeCommandments\Support\Pipes\Php\FindStringsThatShouldBeEnums;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

/**
 * Flag raw string literals that are really enum cases in disguise.
 *
 * When a file imports an enum and then passes `direction: 'input'` to a
 * constructor (or defaults a `string $direction = 'input'` param),
 * that's almost always `PortDirection::Input` wearing a stringly-typed
 * mask. Stringly-typed values bypass static analysis, IDE refactors,
 * and exhaustive `match`.
 */
#[IntroducedIn('1.6.0')]
class StringsThatShouldBeEnumsProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Use enum cases instead of raw string literals for closed-set values';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Closed-set values — directions, statuses, kinds, modes — belong in
enums, not string literals. This prophet flags two high-signal patterns
that are almost always stringly-typed-disguise-of-an-enum:

1. Named argument passed to a call where the argument name matches an
   enum imported in the file and the value matches one of its cases.

   Bad:
       new Port(direction: 'input');
   Good:
       new Port(direction: PortDirection::Input);

2. A `string`-typed parameter with a default literal matching a case
   on an imported enum with a matching name.

   Bad:
       public function __construct(public readonly string $status = 'running') {}
   Good:
       public function __construct(public readonly WorkflowRunStatus $status = WorkflowRunStatus::Running) {}

Stringly-typed values bypass static analysis, IDE refactors, and
exhaustive match. Wrap them in a `BackedEnum` (or plain enum) and
replace every literal with the matching case.

Exceptions: values inside `toArray`, `jsonSerialize`, `render`, or
inside a `JsonResource`/`Resource`/`Response` class are left alone —
those are wire-format boundaries where the literal string is the
public contract.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        return PhpPipeline::make($filePath, $content)
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractUseStatements::class)
            ->pipe(new FindStringsThatShouldBeEnums)
            ->sinsFromMatches(
                fn ($match) => sprintf(
                    "Raw string literal '%s' for %s — looks like a case of %s imported in this file. Replace with %s::%s.",
                    $match->groups['value'],
                    $match->groups['subject'],
                    $match->groups['enum_short'],
                    $match->groups['enum_short'],
                    $match->groups['case'],
                ),
                fn () => 'Stringly-typed closed-set values bypass static analysis, IDE refactors, and exhaustive match. Use the enum case.'
            )
            ->judge();
    }
}
