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
 * Flag an `env()`-backed config value (a STRING at runtime) strict-compared (`===` /
 * `!==`) to an integer or float literal — the comparison is ALWAYS false/true,
 * because `env()` does not cast numeric strings (only `true`/`false`/`null`/empty).
 * `config('x.timeout') === 30` never matches when the leaf is `env('TIMEOUT')`.
 *
 * The forward-flow slice of #163's config→typed-sink prophet (#5): a mixed config
 * leaf reaching a TYPED comparison without a cast. Cross-artifact via
 * {@see ConfigReadIndex} (which leaves are env-backed). Near-zero-FP: only fires on a
 * strict comparison against a numeric literal where the leaf is provably env-backed
 * in the project's own config. ADVISORY (a WARNING). (The broader config→typed-param/
 * arithmetic flow awaits a forward value-flow tracer.)
 */
#[IntroducedIn('2.20.0')]
class MixedConfigValueUsedTypedProphet extends PhpCommandment
{
    public function description(): string
    {
        return 'An env()-backed config value strict-compared to a number is always false — cast it first';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen(
                'A `config(\'x\')` read whose leaf is `env()`-backed (a string at runtime) '
                . 'is compared with `===`/`!==` against an INTEGER or FLOAT literal. The '
                . 'comparison can never hold — `env()` does not cast numeric strings.'
            )
            ->leaveWhen(
                'the config leaf is a literal int/float in the config file (not env-backed); '
                . 'the comparison is loose (`==`); the value is cast first (`(int) config(...)`); '
                . 'or the key is dynamic.'
            )
            ->whenUnsure(
                'cast at the boundary — `(int) config(\'x\')` / `(float) config(\'x\')` — then '
                . 'the strict comparison is type-correct; or compare against a string literal '
                . 'if the env value really is textual.'
            );
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
`env('X')` returns the raw STRING from the environment, except for the literals
`true`/`false`/`null`/`empty` (cast to bool/null) — it does NOT cast numeric
strings. So a config leaf declared `'timeout' => env('TIMEOUT')` is a string at
runtime, and a strict comparison against a number silently never matches.

Bad — always false (config('cache.ttl') is the string "3600"):
    if (config('cache.ttl') === 3600) { … }   // "3600" !== 3600

Good — cast at the read, then compare:
    if ((int) config('cache.ttl') === 3600) { … }

WHAT FIRES — a `config('literal')` whose leaf is `env()`-backed in the project's own
config, used as one side of a `===`/`!==` against an int/float literal.

WHAT DOES NOT — a literal-typed config leaf, a loose `==`, a cast value, or a dynamic
key. Advisory (a WARNING); not auto-fixable.
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

        $finder = new NodeFinder;
        $warnings = [];

        foreach ($finder->findInstanceOf($ast, Node\Expr\BinaryOp\Identical::class) as $cmp) {
            $this->check($cmp, $index, $warnings);
        }

        foreach ($finder->findInstanceOf($ast, Node\Expr\BinaryOp\NotIdentical::class) as $cmp) {
            $this->check($cmp, $index, $warnings);
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * @param  Node\Expr\BinaryOp\Identical|Node\Expr\BinaryOp\NotIdentical  $cmp
     * @param  list<\JesseGall\CodeCommandments\Results\Warning>  $warnings
     */
    private function check(Node\Expr\BinaryOp $cmp, ConfigReadIndex $index, array &$warnings): void
    {
        $path = $this->envBackedConfigPath($cmp->left, $index) ?? $this->envBackedConfigPath($cmp->right, $index);
        $hasNumeric = $this->isNumericLiteral($cmp->left) || $this->isNumericLiteral($cmp->right);

        if ($path === null || ! $hasNumeric) {
            return;
        }

        $op = $cmp instanceof Node\Expr\BinaryOp\Identical ? '===' : '!==';

        $warnings[] = $this->warningAt(
            $cmp->getStartLine(),
            sprintf(
                "config('%s') is env()-backed — a STRING at runtime — but is strict-compared (`%s`) to a number here. env() does not cast numeric strings, so this comparison is always %s. Cast at the read: `(int) config('%s')` (or `(float)`), then the strict comparison is type-correct.",
                $path,
                $op,
                $op === '===' ? 'false' : 'true',
                $path,
            ),
            null,
            'mixed-config-typed:' . $path,
        );
    }

    private function envBackedConfigPath(Node\Expr $expr, ConfigReadIndex $index): ?string
    {
        if (! $expr instanceof Node\Expr\FuncCall
            || ! $expr->name instanceof Node\Name
            || strtolower($expr->name->toString()) !== 'config'
        ) {
            return null;
        }

        $arg = $expr->getArgs()[0]->value ?? null;

        if (! $arg instanceof Node\Scalar\String_ || ! $index->isEnvBacked($arg->value)) {
            return null;
        }

        return $arg->value;
    }

    private function isNumericLiteral(Node\Expr $expr): bool
    {
        return $expr instanceof Node\Scalar\Int_ || $expr instanceof Node\Scalar\Float_;
    }
}
