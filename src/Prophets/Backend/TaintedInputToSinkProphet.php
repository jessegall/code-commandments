<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\TaintCatalog;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * SECURITY (advisory). Flag request input flowing DIRECTLY into a dangerous sink —
 * raw SQL (`DB::raw`/`->whereRaw`/`DB::statement`), command execution (`exec`/
 * `shell_exec`/`system`/`proc_open`), or `unserialize` — with no sanitizer boundary
 * in that argument. That is an injection / RCE / object-injection vector.
 *
 * Direct-flow via {@see TaintCatalog}: a request source (`request()->input`,
 * `$request->x`, `Input::get`) appearing inside the sink's argument with NO cast /
 * `validated()` / whitelist in that argument. Near-zero-FP: requires the source to be
 * IN the sink argument (no ambiguous tracing) and bails if ANY cast/sanitizer is
 * present. Tier::Correctness; clearly a security finding.
 */
#[IntroducedIn('2.23.0')]
class TaintedInputToSinkProphet extends PhpCommandment
{
    private TaintCatalog $taint;

    public function __construct()
    {
        $this->taint = new TaintCatalog;
    }

    public function description(): string
    {
        return 'SECURITY: request input must not reach raw SQL / exec / unserialize without sanitization';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Correctness;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'Request input (`request()->input`, `$request->x`, `Input::get`) appears '
                . 'directly inside a dangerous sink argument — raw SQL (`DB::raw`/`whereRaw`/'
                . '`DB::statement`), `exec`/`shell_exec`/`system`/`proc_open`, or '
                . '`unserialize` — with no cast/`validated()`/whitelist in that argument.'
            )
            ->leaveWhen(
                'the argument casts the input (`(int)`/`intval`), runs it through '
                . '`validated()` or a whitelist (`in_array`/match), or the value is a literal/'
                . 'constant/bound parameter — Eloquent parameter binding (the non-raw query '
                . 'methods) is safe, only the RAW sinks are dangerous.'
            )
            ->whenUnsure(
                'never concatenate request input into raw SQL or a shell command: use bound '
                . 'parameters (`whereRaw(\'col = ?\', [$id])` or just `where()`), cast/validate '
                . 'the input, or whitelist it against an allowed set.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Request input is attacker-controlled. Concatenated into raw SQL it is SQL injection;
passed to a shell function it is remote code execution; handed to `unserialize` it is
object injection. The fix is always a boundary: bind the parameter, cast it, validate
it, or whitelist it.

Bad — request input straight into raw SQL / a shell:
    DB::statement("DELETE FROM logs WHERE id = " . $request->input('id'));
    exec('convert ' . $request->input('file'));

Good — bind / validate / whitelist:
    DB::statement('DELETE FROM logs WHERE id = ?', [(int) $request->input('id')]);
    $file = $request->validate(['file' => 'in:a.png,b.png'])['file'];

WHAT FIRES — a request source inside a raw-SQL / exec / `unserialize` sink argument
with no cast/`validated()`/whitelist in that argument.

WHAT DOES NOT — a cast or sanitizer is present; the value is a literal/constant; or the
query uses parameter binding (non-raw methods). Advisory (a WARNING), security; not
auto-fixable.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $finder = new NodeFinder;
        $warnings = [];
        $seenLines = [];

        foreach ($finder->find($ast, fn (Node $n) => $this->taint->isDangerousSink($n)) as $sink) {
            if (! $sink instanceof Node\Expr\MethodCall && ! $sink instanceof Node\Expr\StaticCall && ! $sink instanceof Node\Expr\FuncCall) {
                continue;
            }

            // Raw-SQL sinks: only arg 0 (the SQL string) is dangerous — later args are
            // BOUND parameters and are safe. Exec/unserialize sinks: every arg is dangerous.
            $args = $this->taint->isRawSqlSink($sink) ? array_slice($sink->getArgs(), 0, 1) : $sink->getArgs();

            foreach ($args as $arg) {
                $sources = $finder->find([$arg->value], fn (Node $n) => $this->taint->isRequestSource($n));

                if ($sources === [] || $this->taint->hasSanitizer($arg->value, $finder)) {
                    continue;
                }

                $line = $sink->getStartLine();

                if (isset($seenLines[$line])) {
                    break;
                }

                $seenLines[$line] = true;
                $warnings[] = $this->warningAt(
                    $line,
                    'SECURITY: request input flows DIRECTLY into a dangerous sink here (raw SQL / exec / unserialize) with no cast, validation, or whitelist in the argument — an injection / RCE / object-injection vector. Bind the parameter (`whereRaw(\'col = ?\', [$x])`), cast it (`(int)`), or validate/whitelist the input before it reaches the sink.',
                    null,
                    'tainted-input-to-sink:' . $line,
                );

                break;
            }
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }
}
