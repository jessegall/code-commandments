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
 * SECURITY (advisory). Flag a secret flowing DIRECTLY into a log / dump sink — a
 * `config('...secret/key/token/password...')` value or a `$x->password`/`->apiToken`
 * property passed to `Log::*` / `logger()` / `dd`/`dump`/`var_dump` — with no
 * redaction. Secrets in logs leak to anyone who can read them.
 *
 * Direct-flow via {@see TaintCatalog}: a secret source inside a leak-sink argument
 * with NO hash/mask/cast in that argument. Near-zero-FP: requires the secret to be IN
 * the sink argument and bails if any redaction is present. Tier::Correctness;
 * security.
 */
#[IntroducedIn('2.23.0')]
class SecretToLogOrResponseProphet extends PhpCommandment
{
    private TaintCatalog $taint;

    public function __construct()
    {
        $this->taint = new TaintCatalog;
    }

    public function description(): string
    {
        return 'SECURITY: a secret (config token/password, ->password) must not be logged or dumped unredacted';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Correctness;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A secret — `config(\'...secret/key/token/password...\')` or a '
                . '`$x->password`/`->apiToken`/`->secret` property — appears directly inside '
                . 'a log/dump sink (`Log::*`, `logger()`, `dd`/`dump`/`var_dump`) with no '
                . 'redaction (hash/mask/cast) in that argument.'
            )
            ->leaveWhen(
                'the value is hashed/masked/encrypted before logging; the property only '
                . 'NAMES a secret but holds something else; or the sink is a secure secret '
                . 'store, not a log/response.'
            )
            ->whenUnsure(
                'never log a raw secret: log a masked form (`Str::mask($token, \'*\', 4)`), a '
                . 'hash, or just an identifier — and scrub secrets from the value before it '
                . 'reaches a log or an HTTP response.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A secret written to a log or dumped into a response leaks to anyone who can read the
log or the page — a credential disclosure that long outlives the request.

Bad — a raw secret in the log / a dump:
    Log::info('connecting with ' . config('services.stripe.secret'));
    dd($user->password);

Good — redact first:
    Log::info('connecting with key ending ' . Str::mask(config('services.stripe.secret'), '*', -4));

WHAT FIRES — a `config('...secret...')` value or a `->password`/`->apiToken`/`->secret`
property inside a `Log::*` / `logger()` / `dd`/`dump`/`var_dump` argument with no
hash/mask/cast in that argument.

WHAT DOES NOT — the value is hashed/masked/encrypted; the name is incidental; or the
sink is a secure store. Advisory (a WARNING), security; not auto-fixable.
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

        foreach ($finder->find($ast, $this->taint->isLeakSink(...)) as $sink) {
            if (! $sink instanceof Node\Expr\FuncCall && ! $sink instanceof Node\Expr\StaticCall) {
                continue;
            }

            foreach ($sink->getArgs() as $arg) {
                $secrets = $finder->find([$arg->value], $this->taint->isSecretSource(...));

                if ($secrets === [] || $this->taint->hasRedaction($arg->value, $finder)) {
                    continue;
                }

                $line = $sink->getStartLine();

                if (isset($seenLines[$line])) {
                    break;
                }

                $seenLines[$line] = true;
                $warnings[] = $this->warningAt(
                    $line,
                    'SECURITY: a secret (a config token/password value, or a ->password/->apiToken property) is written DIRECTLY to a log/dump sink here with no redaction — it leaks to anyone who can read the log or output. Log a masked form (`Str::mask($secret, \'*\', -4)`), a hash, or an identifier instead.',
                    null,
                    'secret-to-log:' . $line,
                );

                break;
            }
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }
}
