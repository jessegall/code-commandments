<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\ConfigConsumerCensus;
use JesseGall\CodeCommandments\Support\ConfigReadIndex;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Flag a distinctive literal hardcoded into a consumer that — ELSEWHERE in the
 * codebase — is fed a `config()` read of the very path whose value equals that
 * literal. The codebase reads the value from config in one place and hardcodes it in
 * another (provable drift): read it from config here too.
 *
 * Cross-artifact value-flow congruence via {@see ConfigConsumerCensus} (which consumer
 * APIs receive a `config('P')` read) + {@see ConfigReadIndex} (the value→path map).
 * Near-zero-FP — it does NOT fire on bare value equality (the FP trap: `'Y-m-d'`,
 * `'google-drive'`, `'local'`): it requires (1) the SAME consumer (callee + arg
 * position) to read `config('P')` somewhere, and (2) the literal to be a DISTINCTIVE
 * compound token (a `-`/`:`-separated value, not a bare default like `local`).
 * ADVISORY (a WARNING). GENERIC.
 */
#[IntroducedIn('2.25.0')]
class HardcodedLiteralShouldBeConfigProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'A literal hardcoded into a consumer that reads it from config elsewhere should read from config too';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A distinctive compound literal is passed to a consumer (a method/function '
                . 'at an argument position) that ELSEWHERE in the codebase is fed '
                . '`config(\'P\')`, where `P`\'s declared value equals the literal. The value '
                . 'is read from config in one place and hardcoded in another.'
            )
            ->leaveWhen(
                'no call site feeds `config(\'P\')` to the same consumer (then a value match '
                . 'is coincidental — `local`, `Y-m-d`, a disk id); the literal is a bare '
                . 'word/default (no `-`/`:` separator); the value is declared at >1 config '
                . 'path (ambiguous); or the two uses are genuinely unrelated concerns.'
            )
            ->whenUnsure(
                'if this consumer reads the value from config elsewhere, read it from config '
                . "here too (`config('P')`) so the value has one source; if the match is "
                . 'coincidental, leave it.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A value that is read from config in one call but hardcoded in the same kind of call
elsewhere will drift — change the config and the hardcoded copy goes stale. This
fires only on PROVABLE drift: the same consumer (callee + arg position) is fed both
`config('P')` and the raw literal whose value is `P`.

Bad — `onQueue` reads the queue from config in one place, hardcodes it in another:
    dispatch($a)->onQueue(config('queue.assistants'));   // somewhere
    dispatch($b)->onQueue('ai-assistants');              // here — same value, hardcoded

Good — one source:
    dispatch($b)->onQueue(config('queue.assistants'));

WHAT FIRES — a DISTINCTIVE compound literal (a `-`/`:`-separated token) passed to a
consumer that elsewhere receives `config('P')`, where `P`'s value equals the literal.

WHAT DOES NOT — bare value equality with no congruent config read (the coincidence
trap: `local`, `Y-m-d`, `google-drive`); a bare-word/default literal; a value at
multiple config paths. Advisory (a WARNING); not auto-fixable.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $index = ConfigReadIndex::forFile($filePath);
        $census = ConfigConsumerCensus::forFile($filePath);

        if ($index->isEmpty() || $census->isEmpty()) {
            return $this->righteous();
        }

        $finder = new NodeFinder;
        $warnings = [];
        $seen = [];

        foreach ([Node\Expr\MethodCall::class, Node\Expr\NullsafeMethodCall::class, Node\Expr\StaticCall::class, Node\Expr\FuncCall::class] as $type) {
            foreach ($finder->findInstanceOf($ast, $type) as $call) {
                if (method_exists($call, 'isFirstClassCallable') && $call->isFirstClassCallable()) {
                    continue;
                }

                $callee = ConfigConsumerCensus::calleeName($call);

                if ($callee === null) {
                    continue;
                }

                foreach ($call->getArgs() as $i => $arg) {
                    if (! $arg->value instanceof Node\Scalar\String_) {
                        continue;
                    }

                    $value = $arg->value->value;
                    $key = $callee . '@' . $i . '|' . $value;

                    if (isset($seen[$key]) || ! $this->isDistinctive($value)) {
                        continue;
                    }

                    $path = $index->pathForValue($value);

                    if ($path === null || ! $census->readsPath($callee, $i, $path)) {
                        continue;
                    }

                    $seen[$key] = true;
                    $warnings[] = $this->warningAt(
                        $call->getStartLine(),
                        sprintf(
                            "The literal '%s' is hardcoded into `%s()` here, but the same consumer reads it from config('%s') elsewhere (that config value IS '%s'). The value now lives in two places and will drift — read it from config here too: `config('%s')`.",
                            $value,
                            $callee,
                            $path,
                            $value,
                            $path,
                        ),
                        null,
                        'hardcoded-literal-config:' . $callee . ':' . $value,
                    );
                }
            }
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * A distinctive compound token: a `-`/`:`-separated alphanumeric value, length >= 5,
     * no path/URL/whitespace chars. Excludes bare words / defaults (`local`, `default`)
     * that coincidentally match a config value.
     */
    private function isDistinctive(string $value): bool
    {
        if (strlen($value) < 5 || preg_match('#[\s/\\\\${}<>]#', $value)) {
            return false;
        }

        return (bool) preg_match('/[a-z0-9][-:][a-z0-9]/i', $value);
    }
}
