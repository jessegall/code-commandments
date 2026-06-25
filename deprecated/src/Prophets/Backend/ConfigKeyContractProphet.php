<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\ConfigReadIndex;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Flag a `config('a.b.c')` read whose path is NOT declared in the project's config
 * tree — a typo or a removed key. A `config()` miss returns `null` SILENTLY, so a
 * mistyped key is an invisible bug until something downstream breaks.
 *
 * Cross-artifact via {@see ConfigReadIndex}: the read's literal path is checked
 * against the declared key tree of `config/*.php`. To stay near-zero-FP it fires
 * ONLY on a read whose TOP-LEVEL segment is an OWNED config file (`config/foo.php`
 * exists) but whose deeper path is absent — i.e. a typo within YOUR own config. A
 * read under a framework/vendor namespace (no `config/foo.php` to check against) is
 * left alone. ADVISORY (a WARNING). GENERIC: pure config-array shape + the literal
 * read; no Laravel coupling beyond the `config()`/`Config::get()` accessor names.
 */
#[IntroducedIn('2.14.0')]
class ConfigKeyContractProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'A config() read must target a declared config key — a missing path is a silent null';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A `config(\'a.b.c\')` / `Config::get(\'a.b.c\')` read uses a literal path '
                . 'whose TOP-LEVEL segment is an OWNED config file (`config/a.php` exists) '
                . 'but whose full path is NOT declared in that file — a typo or a removed '
                . 'key. The read returns `null` silently.'
            )
            ->leaveWhen(
                'the key is dynamic (`config($var)` / a computed/concatenated path); the '
                . 'top-level namespace is framework/vendor (no `config/<top>.php` to check '
                . 'against); or the path IS declared (env()-backed leaf still counts — the '
                . 'PATH exists even when the value is runtime).'
            )
            ->whenUnsure(
                'if the path should exist, add it to the config file; if it is a typo, fix '
                . 'it to the declared sibling. A `config()` miss is a silent null — make the '
                . 'key match the declared tree.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
`config('a.b.c')` returns the value at that path, or `null` when the path does not
exist — SILENTLY. A mistyped or removed key therefore reads as `null` with no
error, surfacing as a confusing downstream failure far from the typo.

Bad — a typo'd read (the config declares `services.stripe.key`):
    $key = config('servces.stripe.key');   // null, silently — typo of `services`

Good — the declared path:
    $key = config('services.stripe.key');

WHAT FIRES — a `config('…')` / `Config::get('…')` read with a LITERAL dotted path
whose first segment is an OWNED config file (`config/<segment>.php` exists in the
project) but whose full path is not declared in the config tree.

WHAT DOES NOT — a dynamic key (`config($var)` / concatenated path); a framework or
vendor namespace with no `config/<top>.php` to verify against; a path that IS
declared (an `env()`-backed leaf still declares its PATH). Advisory (a WARNING);
not auto-fixable.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $index = ConfigReadIndex::forFile($filePath);

        if ($index->isEmpty()) {
            return $this->righteous();
        }

        $warnings = [];

        foreach ($this->readPaths($ast) as [$path, $line]) {
            if (! str_contains($path, '.')) {
                continue; // a whole-file read (`config('app')`) — nothing to verify
            }

            $top = strtok($path, '.');

            if ($top === false || ! $index->ownsTopLevel($top) || $index->hasPath($path)) {
                continue;
            }

            $siblings = $index->siblingsOf($path);
            $hint = $siblings === [] ? '' : sprintf(' Declared siblings: %s.', implode(', ', $siblings));

            $warnings[] = $this->warningAt(
                $line,
                sprintf(
                    "config('%s') reads a key NOT declared in the config tree. The `%s` config namespace exists (config/%s.php), but `%s` is not a declared path in it — a typo or a removed key, and a config() miss returns null SILENTLY.%s Fix the key to match the declared tree (or add it to config/%s.php).",
                    $path,
                    $top,
                    $top,
                    $path,
                    $hint,
                    $top,
                ),
                null,
                'config-key-contract:' . $path,
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * Every `config('literal')` / `Config::get('literal')` read with a string-literal
     * first argument: [path, line].
     *
     * @param  array<Node>  $ast
     * @return list<array{0: string, 1: int}>
     */
    private function readPaths(array $ast): array
    {
        $finder = new NodeFinder;
        $reads = [];

        foreach ($finder->findInstanceOf($ast, Node\Expr\FuncCall::class) as $call) {
            if ($call->name instanceof Node\Name && strtolower($call->name->toString()) === 'config') {
                $literal = $this->firstStringArg($call->getArgs());

                if ($literal !== null) {
                    $reads[] = [$literal, $call->getStartLine()];
                }
            }
        }

        foreach ($finder->findInstanceOf($ast, Node\Expr\StaticCall::class) as $call) {
            if ($call->class instanceof Node\Name
                && $call->class->getLast() === 'Config'
                && $call->name instanceof Node\Identifier
                && strtolower($call->name->toString()) === 'get'
            ) {
                $literal = $this->firstStringArg($call->getArgs());

                if ($literal !== null) {
                    $reads[] = [$literal, $call->getStartLine()];
                }
            }
        }

        return $reads;
    }

    /**
     * @param  list<Node\Arg>  $args
     */
    private function firstStringArg(array $args): ?string
    {
        $first = $args[0] ?? null;

        return $first instanceof Node\Arg && $first->value instanceof Node\Scalar\String_
            ? $first->value->value
            : null;
    }
}
