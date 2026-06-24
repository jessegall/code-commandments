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
 *
 *
 *
 *
 * @method-generated-start
 * @method static crossFileTrace(bool $on = true)
 * @method-generated-end
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

4. Array of string literals as a closed-set membership test.

   Bad:
       in_array($type, ['string', 'int', 'float', 'bool'], true)
   Good:
       in_array($type, [FieldType::String, FieldType::Int, FieldType::Float, FieldType::Bool], true)

   When every literal in an `in_array(...)` / `array_search(...)` array
   is a case of one name-matched enum, the bare strings are an enum in
   disguise — build the set from the enum's cases instead.

5. Branching on string literals — `match`, `switch`, `if`/`elseif`.

   Bad:
       match ($port->kind) { 'input' => …, 'output' => … }
       switch ($port->kind) { case 'input': …; case 'output': … }
       if ($port->kind === 'input') { … } elseif ($port->kind === 'output') { … }
   Good:
       match ($port->kind) { PortKind::Input => …, PortKind::Output => … }

   When the arms / cases / chain conditions are all string literals that
   are cases of one name-matched enum, type the subject as the enum and
   branch on its cases — bare strings bypass exhaustiveness and refactors.

IMPORTANT: the matched enum is only the closest EXISTING one (by name
and cases) — it is a CANDIDATE, not a requirement. The fix is "make
this a typed enum value", NOT "reuse that specific enum". If the match
models a different concern — a schema field type where you really want
a port type, say — prefer creating a new, purpose-specific enum over
forcing the value into an unrelated one. Reuse the matched enum only
when it is genuinely the right home.

Exceptions: values inside `toArray`, `jsonSerialize`, `render`, or
inside a `JsonResource`/`Resource`/`Response` class are left alone —
those are wire-format boundaries where the literal string is the
public contract.

Named args passed to a `new VendorClass(...)`, `VendorClass::m(...)`,
or `#[VendorAttribute(...)]` where the target class file lives under
`/vendor/` are also skipped — the consumer can't change a third-party
signature to take an enum.
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
                $this->messageFor(...),
                $this->suggestionFor(...),
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

        if ($match->name === 'control_flow_closed_set') {
            return sprintf(
                "%s branches on string literals [%s] of %s — every value is a case of %s. Type %s as %s and branch on its cases (%s) instead of raw strings.",
                ucfirst((string) ($groups['kind'] ?? 'match')),
                $groups['literals'],
                $groups['subject'],
                $groups['enum_short'],
                $groups['subject'],
                $groups['enum_short'],
                $groups['enum_cases'],
            );
        }

        if ($match->name === 'literal_array_closed_set') {
            return sprintf(
                "Closed set of string literals [%s] tested against %s — a one-of test where every value is a case of %s. Collapse it with the CompareSelf helper: %s::equalsAny(%s, %s) (static, since %s is a value), or %s->equalsAny(...) once %s is the enum. Don't rebuild it as a raw in_array.",
                $groups['literals'],
                $groups['subject'],
                $groups['enum_short'],
                $groups['enum_short'],
                $groups['subject'],
                $groups['enum_cases'],
                $groups['subject'],
                $groups['subject'],
                $groups['subject'],
            );
        }

        $unimported = $groups['requires_import'] === '1';

        return sprintf(
            "Raw string literal '%s' for %s belongs in an enum. %s::%s is the closest existing match%s — reuse it if %s is the right home, otherwise introduce a purpose-specific enum.",
            $groups['value'],
            $groups['subject'],
            $groups['enum_short'],
            $groups['case'],
            $unimported ? " (add `use {$groups['enum_fqcn']};`)" : '',
            $groups['enum_short'],
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

        if ($match->name === 'control_flow_closed_set') {
            $importHint = ($groups['requires_import'] ?? '') === '1'
                ? sprintf('Import `%s`, then ', $groups['enum_fqcn'])
                : '';

            return sprintf(
                '%sthe branch labels are cases of `%s` — make %s an `%s` and switch on its cases (%s). If a different concern, a purpose-specific enum may fit better.',
                $importHint,
                $groups['enum_short'],
                $groups['subject'],
                $groups['enum_short'],
                $groups['enum_cases'],
            );
        }

        if ($match->name === 'literal_array_closed_set') {
            $importHint = ($groups['requires_import'] ?? '') === '1'
                ? sprintf('Import `%s`, then ', $groups['enum_fqcn'])
                : '';

            return sprintf(
                '%sreplace the membership test with `%s::equalsAny(%s, %s)` (CompareSelf). If %s is not the right home, a purpose-specific enum may fit better.',
                $importHint,
                $groups['enum_short'],
                $groups['subject'],
                $groups['enum_cases'],
                $groups['enum_short'],
            );
        }

        $base = 'Stringly-typed closed-set values bypass static analysis, IDE refactors, and exhaustive match. Use an enum case — the matched enum is the closest EXISTING one, but creating a new purpose-specific enum is often the better fit.';

        if (($groups['requires_import'] ?? '') === '1') {
            return $base . sprintf(' Add `use %s;` to this file.', $groups['enum_fqcn']);
        }

        return $base;
    }
}
