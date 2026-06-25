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
use JesseGall\CodeCommandments\Support\Pipes\Php\FindInvokableConstructWithStaticHelper;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

/**
 * Flag `(new X(...))(...)` call sites where `X` is project-owned.
 *
 * The invokable-construct shape is verbose, leaks construction detail at
 * every call site, and bypasses any factory logic the class may add. A
 * static factory (`X::for(...)`, `X::make(...)`) reads better and lets
 * the class evolve without touching every caller.
 *
 * Severity:
 * - `(new X(...))()` — no invocation args → sin.  The static replacement
 *   is unambiguous.
 * - `(new X(...))(arg, ...)` — has invocation args → warning. The static
 *   factory might not encode every invocation mode 1:1, so this is
 *   surfaced for review rather than failing the run.
 *
 * Vendor classes (target file lives under `/vendor/`) are skipped — the
 * consumer can't refactor a third-party class.
 */
#[IntroducedIn('1.8.0')]
class PreferStaticOverInvokableConstructProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'Prefer a static factory over (new X(...))(...) for project-owned classes';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Constructing a class only to immediately invoke it — `(new X(...))(...)`
— is verbose, leaks the construction detail at every call site, and
bypasses any factory logic the class may add later. A static factory
(`X::for(...)`, `X::make(...)`) reads better and keeps the construction
shape inside the class.

Bad:
    (new UserPermissionCache($userId))();
    (new UserPermissionCache($userId))(null);

Good:
    UserPermissionCache::for($userId);
    UserPermissionCache::forget($userId);

Severity depends on the call shape:

- `(new X(...))()` (no invocation args) is reported as a sin. There's
  no second-axis behaviour — a `static for(...)` / `static make(...)`
  unambiguously replaces the call.

- `(new X(...))(arg, ...)` (has invocation args) is reported as a
  warning. Invocation args mean the class supports more than one
  invocation mode, and a single static factory may not cover every
  case 1:1. Worth a look, not worth failing the run.

Vendor classes (target file lives under `/vendor/`) are skipped — the
consumer can't refactor a third-party class.

Out of scope:
- `new X(...)` passed as a callable (e.g. `Pipeline::through([new Foo()])`)
  — the construction is not invoked at the call site.
- `(new class { ... })()` — anonymous class invocation.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $pipeline = PhpPipeline::make($filePath, $content)
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractUseStatements::class)
            ->pipe(new FindInvokableConstructWithStaticHelper);

        if ($pipeline->shouldSkip()) {
            return $pipeline->judge();
        }

        $sins = [];
        $warnings = [];

        foreach ($pipeline->getContext()->matches as $match) {
            if ($match->groups['has_invoke_args'] === '1') {
                $warnings[] = $this->buildWarning($match);
            } else {
                $sins[] = $this->buildSin($match);
            }
        }

        if ($sins === [] && $warnings === []) {
            return Judgment::righteous();
        }

        return new Judgment(sins: $sins, warnings: $warnings);
    }

    private function buildSin(MatchResult $match): Sin
    {
        $class = $match->groups['class'];
        $statics = $match->groups['static_names'];

        $message = sprintf('(new %s(...))() — replace with a static factory call.', $class);

        $suggestion = $statics !== ''
            ? sprintf('Use %s instead so the construction stays inside the class.', $statics)
            : sprintf(
                'Introduce a static factory on %s (e.g. %s::for(...) or %s::make(...)) and call that instead.',
                $class, $class, $class,
            );

        return Sin::at($match->line, $message, $match->content, $suggestion);
    }

    private function buildWarning(MatchResult $match): Warning
    {
        $class = $match->groups['class'];
        $statics = $match->groups['static_names'];

        $message = $statics !== ''
            ? sprintf(
                '(new %s(...))(arg) — consider %s. Invocation args may not map 1:1 to the static helper, review before replacing.',
                $class, $statics,
            )
            : sprintf(
                '(new %s(...))(arg) — consider replacing with a static factory on %s (review: invocation args may not map 1:1).',
                $class, $class,
            );

        return Warning::at($match->line, $message, $match->content);
    }
}
