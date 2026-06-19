<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Results\Warning;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Flag a coerce-or-default ternary — `is_numeric($x) ? (int) $x : $default` —
 * that recurs in a class. The same guard+cast+fallback hand-rolled in several
 * methods should be ONE named typed-accessor helper (like the `stringOr()` such
 * classes already tend to have), so the coercion rule lives in a single place.
 */
#[IntroducedIn('1.116.0')]
class PreferCoercionHelperProphet extends PhpCommandment implements SinRepenter
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
            ->applyWhen('The SAME coerce-or-default shape — a type-guard ternary like `is_numeric($x) ? (int) $x : $default` — is hand-rolled in two or more methods of one class. Route it through the validated php-types helper `T_Int::coerce($x, $default)` (T_String/T_Float/T_Bool) — it guards the type and falls back, never blind-casts.')
            ->leaveWhen('the coercion appears only once (no duplication to hoist), or each site genuinely differs (different guard, cast, or shape) so a shared helper would not fit.')
            ->whenUnsure('replace the repeated ternary with `T_String::coerce()`/`T_Int::coerce()`/…; a per-class `stringOr()`-style helper just re-duplicates the body across classes (and trips DuplicateCode) — the `T_*::coerce()` home ends that. A lone coercion can stay inline.');
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

Good — the validated php-types helper, every site routes through it:
    public function maxInputTokens(): int
    {
        return T_Int::coerce($this->config->get('….max_input_tokens'), 80_000);
    }
    public function maxBodyBytes(): int
    {
        return T_Int::coerce($this->config->get('….max_body_bytes'), 1_048_576);
    }

`T_Int::coerce()` (and T_String/T_Float/T_Bool) guards the type and falls back —
`coerce("abc", $d)` is `$d`, never `0` — unlike `coalesce()`, which blind-casts.
A per-class `intOr()` helper would just re-duplicate the same body across every
config/accessor class (and then trip DuplicateCode); the `T_*::coerce()` home
ends that.

WHAT FIRES — a ternary whose condition type-guards a variable `$v` (`is_numeric`,
`is_string`, `is_array`, … possibly inside `&&`/`!`) and whose branches coerce
that same `$v` (`(int)$v`/`(float)$v`/`(string)$v`/`(bool)$v`, or keep `$v`)
against a default — when the SAME shape (guard + cast kind) appears 2+ times in
one class.

WHAT DOES NOT — a single occurrence (no duplication), differing shapes, or a
plain `?? / ?:` fallback chain (that is RepeatedFallback's job — this rule is
specifically type-guarded COERCION).

[AUTO-FIXABLE] for the EXACT-semantic shapes: `repent` rewrites
`is_numeric($x) ? (int|float) $x : D` to `T_Int/T_Float::coerce($x, D)` and
`is_scalar($x) ? (string) $x : D` to `T_String::coerce($x, D)` (a `null` fallback
→ `coerceOrNull($x)`), adding the `use` import. It LEAVES `is_string`-guarded
sites (coerce accepts any scalar — broader), a fallback that is a computed
alternative rather than a default, and a coercion wrapped in more work.
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
                $this->autofixPlan($occ['ternary']) !== null,
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

    /**
     * The mechanical-rewrite plan for a coercion ternary, or null when it is not
     * safely auto-fixable (#90). Only the EXACT-semantic shapes are auto-fixed —
     * `is_numeric ? (int|float)` ↔ T_Int/T_Float::coerce, `is_scalar ? (string)`
     * ↔ T_String::coerce — and only when the fallback is a plain default (not a
     * computed alternative). `is_string`-guarded sites stay advisory: T_String
     * ::coerce accepts any scalar (is_scalar), broader than `is_string`.
     *
     * @return array{helper: string, method: string, var: Expr\Variable, default: ?Expr}|null
     */
    private function autofixPlan(Expr\Ternary $ternary): ?array
    {
        if ($ternary->if === null) {
            return null;
        }

        $guard = $this->guardedVariable($ternary->cond);

        if ($guard === null) {
            return null;
        }

        // Identify the coercing branch and the fallback (the other branch).
        $castBranch = null;
        $fallback = null;
        $cast = null;

        foreach ([[$ternary->if, $ternary->else], [$ternary->else, $ternary->if]] as [$candidate, $other]) {
            $c = $this->castOfVariable($candidate, $guard['var']);

            if ($c !== null) {
                $castBranch = $candidate;
                $fallback = $other;
                $cast = $c;
                break;
            }
        }

        if ($castBranch === null || $fallback === null) {
            return null;
        }

        // The coercing branch must be JUST the cast/keep of $var — not wrapped in
        // more computation (e.g. `max(0, (int) $x)`), or coerce() would drop it.
        if (! $this->isBareCoercion($castBranch, $guard['var'])) {
            return null;
        }

        // Exact-semantic guard+cast pairs only.
        $helper = match ([$guard['predicate'], $cast]) {
            ['is_numeric', 'int'] => 'T_Int',
            ['is_numeric', 'float'] => 'T_Float',
            ['is_scalar', 'string'] => 'T_String',
            default => null,
        };

        if ($helper === null) {
            return null;
        }

        // The fallback must be a plain default, not a computed alternative
        // (`(string) json_encode($x)`): no call may appear in it.
        if ($this->containsCall($fallback)) {
            return null;
        }

        $isNullDefault = $fallback instanceof Expr\ConstFetch
            && $fallback->name instanceof Node\Name
            && strtolower($fallback->name->toString()) === 'null';

        return [
            'helper' => $helper,
            'method' => $isNullDefault ? 'coerceOrNull' : 'coerce',
            'var' => $castBranch instanceof Expr\Variable ? $castBranch : $this->varNode($guard['var'], $castBranch),
            'default' => $isNullDefault ? null : $fallback,
        ];
    }

    private function isBareCoercion(Expr $branch, string $var): bool
    {
        if ($branch instanceof Expr\Variable && $branch->name === $var) {
            return true;
        }

        return ($branch instanceof Expr\Cast)
            && $branch->expr instanceof Expr\Variable && $branch->expr->name === $var;
    }

    private function varNode(string $var, Expr $branch): Expr\Variable
    {
        if ($branch instanceof Expr\Cast && $branch->expr instanceof Expr\Variable) {
            return $branch->expr;
        }

        return new Expr\Variable($var);
    }

    private function containsCall(Expr $expr): bool
    {
        return (new NodeFinder)->findFirst([$expr], static fn (Node $n): bool =>
            $n instanceof Expr\FuncCall
            || $n instanceof Expr\MethodCall
            || $n instanceof Expr\StaticCall
            || $n instanceof Expr\NullsafeMethodCall
            || $n instanceof Expr\New_) !== null;
    }

    public function canRepent(string $filePath): bool
    {
        return pathinfo($filePath, PATHINFO_EXTENSION) === 'php';
    }

    public function repent(string $filePath, string $content): RepentanceResult
    {
        if (! $this->canRepent($filePath)) {
            return RepentanceResult::unchanged();
        }

        $ast = (new ParserFactory)->createForNewestSupportedVersion()->parse($content);

        if ($ast === null) {
            return RepentanceResult::unrepentant('Unable to parse PHP file');
        }

        $finder = new NodeFinder;
        $min = $this->minOccurrences();
        $edits = [];
        $penance = [];
        $imports = [];

        // Mirror judge's gating: only rewrite a shape that recurs >= min times in
        // its class (so repent never touches what judge left righteous).
        foreach ($finder->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            $counts = [];
            $plans = [];

            foreach ($class->getMethods() as $method) {
                if ($method->stmts === null) {
                    continue;
                }

                foreach ($finder->findInstanceOf($method->stmts, Expr\Ternary::class) as $ternary) {
                    $coercion = $this->coercion($ternary);

                    if ($coercion === null) {
                        continue;
                    }

                    $counts[$coercion['shape']] = ($counts[$coercion['shape']] ?? 0) + 1;

                    $plan = $this->autofixPlan($ternary);

                    if ($plan !== null) {
                        $plans[] = ['ternary' => $ternary, 'plan' => $plan, 'shape' => $coercion['shape']];
                    }
                }
            }

            foreach ($plans as $entry) {
                if (($counts[$entry['shape']] ?? 0) < $min) {
                    continue;
                }

                $ternary = $entry['ternary'];
                $plan = $entry['plan'];
                $varSrc = substr($content, (int) $plan['var']->getStartFilePos(), (int) $plan['var']->getEndFilePos() - (int) $plan['var']->getStartFilePos() + 1);

                $args = $varSrc;

                if ($plan['method'] === 'coerce' && $plan['default'] !== null) {
                    $args .= ', ' . substr($content, (int) $plan['default']->getStartFilePos(), (int) $plan['default']->getEndFilePos() - (int) $plan['default']->getStartFilePos() + 1);
                }

                $replacement = sprintf('%s::%s(%s)', $plan['helper'], $plan['method'], $args);

                $edits[] = ['start' => (int) $ternary->getStartFilePos(), 'end' => (int) $ternary->getEndFilePos(), 'text' => $replacement];
                $penance[] = sprintf('Replaced a coercion ternary with %s::%s()', $plan['helper'], $plan['method']);
                $imports['JesseGall\\PhpTypes\\' . $plan['helper']] = true;
            }
        }

        if ($edits === []) {
            return RepentanceResult::unchanged();
        }

        usort($edits, static fn (array $a, array $b): int => $b['start'] <=> $a['start']);

        foreach ($edits as $edit) {
            $content = substr($content, 0, $edit['start']) . $edit['text'] . substr($content, $edit['end'] + 1);
        }

        foreach (array_keys($imports) as $fqcn) {
            $content = $this->ensureUse($content, $fqcn);
        }

        return RepentanceResult::absolved($content, $penance);
    }

    private function ensureUse(string $content, string $fqcn): string
    {
        if (preg_match('/^\s*use\s+' . preg_quote($fqcn, '/') . '\s*;/m', $content) === 1) {
            return $content;
        }

        if (preg_match('/^namespace\s+[^;]+;/m', $content, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return $content;
        }

        $insertAt = $m[0][1] + strlen($m[0][0]);

        return substr($content, 0, $insertAt) . "\n\nuse {$fqcn};" . substr($content, $insertAt);
    }

    private function messageFor(string $shape, string $cast, int $count, string $class): string
    {
        [$predicate] = explode(':', $shape);

        $rendered = $cast === 'keep'
            ? sprintf('%s($x) ? $x : default', $predicate)
            : sprintf('%s($x) ? (%s) $x : default', $predicate, $cast);

        $helper = match ($cast) {
            'int' => 'T_Int::coerce($x, $default)',
            'float' => 'T_Float::coerce($x, $default)',
            'bool' => 'T_Bool::coerce($x, $default)',
            default => 'T_String::coerce($x, $default)', // string / keep (is_string ? $x)
        };

        return sprintf(
            'This `%s` coercion appears %d× in %s — replace it with the validated php-types helper `%s` (it guards the type and falls back, never blind-casts garbage), so the cast and fallback live in one canonical place instead of a per-class helper that just re-duplicates.',
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
