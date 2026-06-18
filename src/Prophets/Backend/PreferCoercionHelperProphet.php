<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Results\Warning;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Flag a coerce-or-default ternary — `is_numeric($x) ? (int) $x : $default` —
 * that recurs in a class. The same guard+cast+fallback hand-rolled in several
 * methods should be ONE named typed-accessor helper (like the `stringOr()` such
 * classes already tend to have), so the coercion rule lives in a single place.
 */
#[IntroducedIn('1.116.0')]
class PreferCoercionHelperProphet extends PhpCommandment
{
    private const TYPE_PREDICATES = [
        'is_numeric', 'is_string', 'is_int', 'is_integer',
        'is_float', 'is_double', 'is_bool', 'is_array', 'is_scalar',
    ];

    public function description(): string
    {
        return 'Extract a repeated inline cast-with-fallback (is_x($v) ? (cast) $v : default) into a named coercion helper';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('The SAME coerce-or-default shape — a type-guard ternary like `is_numeric($x) ? (int) $x : $default` — is hand-rolled in two or more methods of one class. The cast + fallback should be a single named helper (intOr/floatOr/boolOr/stringOr…).')
            ->leaveWhen('the coercion appears only once (no duplication to hoist), or each site genuinely differs (different guard, cast, or shape) so a shared helper would not fit.')
            ->whenUnsure('if you already have a `stringOr()`-style accessor, add the int/float/bool/list sibling and route the repeated sites through it; if it is a lone coercion, leave it inline.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A typed accessor over an untyped source (config, request, an array bag) is a
good pattern — but only if the coercion lives in ONE place. When the same
`type-guard ? cast : default` ternary is copy-pasted across methods, the cast
rule (and its fallback) drifts and every new key repeats the dance.

Bad — the same coercion inline in every method:
    public function maxInputTokens(): int
    {
        $v = $this->config->get('….max_input_tokens', 80_000);

        return is_numeric($v) ? (int) $v : 80_000;
    }

    public function maxBodyBytes(): int
    {
        $v = $this->config->get('….max_body_bytes', 1_048_576);

        return is_numeric($v) ? (int) $v : 1_048_576;   // same shape again
    }

Good — one named helper, every site routes through it:
    public function maxInputTokens(): int { return $this->intOr('….max_input_tokens', 80_000); }
    public function maxBodyBytes(): int  { return $this->intOr('….max_body_bytes', 1_048_576); }

    private function intOr(string $key, int $default): int
    {
        $v = $this->config->get($key, $default);

        return is_numeric($v) ? (int) $v : $default;
    }

WHAT FIRES — a ternary whose condition type-guards a variable `$v` (`is_numeric`,
`is_string`, `is_array`, … possibly inside `&&`/`!`) and whose branches coerce
that same `$v` (`(int)$v`/`(float)$v`/`(string)$v`/`(bool)$v`, or keep `$v`)
against a default — when the SAME shape (guard + cast kind) appears 2+ times in
one class. Sibling to the `stringOr`/`stringList` helpers such classes already
have.

WHAT DOES NOT — a single occurrence (no duplication), differing shapes, or a
plain `?? / ?:` fallback chain (that is RepeatedFallback's job — this rule is
specifically type-guarded COERCION).
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $warnings = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            $this->judgeClass($class, $content, $warnings);
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * @param  list<Warning>  $warnings
     */
    private function judgeClass(Node\Stmt\Class_ $class, string $content, array &$warnings): void
    {
        $finder = new NodeFinder;
        $occurrences = []; // list of array{shape, cast, ternary, method}
        $counts = [];

        foreach ($class->getMethods() as $method) {
            if ($method->stmts === null) {
                continue;
            }

            foreach ($finder->findInstanceOf($method->stmts, Expr\Ternary::class) as $ternary) {
                $coercion = $this->coercion($ternary);

                if ($coercion === null) {
                    continue;
                }

                $occurrences[] = $coercion + ['ternary' => $ternary, 'method' => $method->name->toString()];
                $counts[$coercion['shape']] = ($counts[$coercion['shape']] ?? 0) + 1;
            }
        }

        $min = $this->minOccurrences();

        foreach ($occurrences as $occ) {
            if (($counts[$occ['shape']] ?? 0) < $min) {
                continue;
            }

            $warnings[] = $this->warningAt(
                $occ['ternary']->getStartLine(),
                $this->messageFor($occ['shape'], $occ['cast'], $counts[$occ['shape']], $class->name?->toString() ?? 'this class'),
                $this->getLineSnippet($content, $occ['ternary']->getStartLine()),
                'prefer-coercion:' . $occ['shape'] . ':' . $occ['method'],
            );
        }
    }

    /**
     * When $ternary is `is_x($v) ? (cast) $v : default` (either arm coercing
     * $v), its shape ("is_numeric:int") + cast kind; else null.
     *
     * @return array{shape: string, cast: string}|null
     */
    private function coercion(Expr\Ternary $ternary): ?array
    {
        // PHP `?:` short-ternary has no `if`; skip — that is a fallback, not a coercion.
        if ($ternary->if === null) {
            return null;
        }

        $guard = $this->guardedVariable($ternary->cond);

        if ($guard === null) {
            return null;
        }

        foreach ([$ternary->if, $ternary->else] as $branch) {
            $cast = $this->castOfVariable($branch, $guard['var']);

            if ($cast !== null) {
                return ['shape' => $guard['predicate'] . ':' . $cast, 'cast' => $cast];
            }
        }

        return null;
    }

    /**
     * The type predicate + variable name guarded by $cond (handling `!` and the
     * operands of `&&`/`||`).
     *
     * @return array{predicate: string, var: string}|null
     */
    private function guardedVariable(Expr $cond): ?array
    {
        foreach ((new NodeFinder)->findInstanceOf([$cond], Expr\FuncCall::class) as $call) {
            if (! $call->name instanceof Node\Name) {
                continue;
            }

            $name = strtolower($call->name->toString());

            if (! in_array($name, self::TYPE_PREDICATES, true)) {
                continue;
            }

            $arg = $call->args[0] ?? null;

            if ($arg instanceof Node\Arg && $arg->value instanceof Expr\Variable && is_string($arg->value->name)) {
                return ['predicate' => $name, 'var' => $arg->value->name];
            }
        }

        return null;
    }

    /**
     * The cast kind applied to $var within $branch — '(int)$var' anywhere
     * (even wrapped, e.g. max(.., (int)$var)) is 'int'; a bare `$var` is 'keep'.
     */
    private function castOfVariable(Expr $branch, string $var): ?string
    {
        $casts = [
            Node\Expr\Cast\Int_::class => 'int',
            Node\Expr\Cast\Double::class => 'float',
            Node\Expr\Cast\String_::class => 'string',
            Node\Expr\Cast\Bool_::class => 'bool',
        ];

        foreach ($casts as $node => $kind) {
            foreach ((new NodeFinder)->findInstanceOf([$branch], $node) as $cast) {
                /** @var Node\Expr\Cast $cast */
                if ($cast->expr instanceof Expr\Variable && $cast->expr->name === $var) {
                    return $kind;
                }
            }
        }

        if ($branch instanceof Expr\Variable && $branch->name === $var) {
            return 'keep';
        }

        return null;
    }

    private function messageFor(string $shape, string $cast, int $count, string $class): string
    {
        [$predicate] = explode(':', $shape);

        $rendered = $cast === 'keep'
            ? sprintf('%s($x) ? $x : default', $predicate)
            : sprintf('%s($x) ? (%s) $x : default', $predicate, $cast);

        $helper = match ($cast) {
            'int' => 'intOr($key, $default)',
            'float' => 'floatOr($key, $default)',
            'string' => 'stringOr($key, $default)',
            'bool' => 'boolOr($key, $default)',
            default => 'a typed accessor (e.g. stringOr()/stringOrNull())',
        };

        return sprintf(
            'This `%s` coercion appears %d× in %s — extract a named helper (%s) so the cast and fallback live in one place.',
            $rendered,
            $count,
            $class,
            $helper,
        );
    }

    private function minOccurrences(): int
    {
        $min = $this->config('min_occurrences', 2);

        return is_int($min) && $min >= 2 ? $min : 2;
    }

    private function getLineSnippet(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : '';
    }
}
