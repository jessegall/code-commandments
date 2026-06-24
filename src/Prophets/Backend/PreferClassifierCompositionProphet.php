<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\Resolvers\Ast\FileAst;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use PhpParser\PrettyPrinter;

/**
 * Flag a hand-written `||` / `&&` chain of classifier `->matches($x)` checks on the same argument and suggest composing them with `Classifier::anyOf(...)` / `allOf(...)` — one named compound instead of a repeated, drift-prone chain.
 *
 *
 *
 *
 *
 *
 *
 * @method-generated-start
 * @method static classifierBase(string $value)
 * @method-generated-end
 */
#[IntroducedIn('2.71.0')]
class PreferClassifierCompositionProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    private ?CodebaseIndex $index = null;

    private ?PrettyPrinter\Standard $printer = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    public function description(): string
    {
        return 'Compose classifier checks with anyOf()/allOf(), not a ||/&& chain of ->matches()';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('Two or more classifier `->matches($x)` checks on the SAME argument are combined with `||` / `&&` — `DateTimeClassifier::make()->matches($t) || EnumClassifier::make()->matches($t)`. That is what the compound is for: `Classifier::anyOf(...)` (||) / `allOf(...)` (&&) — or `from(A::class, B::class)` — build ONE named classifier you can pass around and reuse, instead of repeating the call.')
            ->leaveWhen('the `matches()` calls are on DIFFERENT arguments (not composable into one classifier check), the receivers are NOT classifiers (a different `matches()` — a string/validator/regex helper), or there is only one check.')
            ->whenUnsure('if each side is `<X>Classifier->matches($same)` OR/AND-ed on the same value, replace the chain with `Classifier::anyOf($a, $b)` / `allOf($a, $b)` (or `from(A::class, B::class)`) and call `->matches($same)` once.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A classifier already composes — `Classifier::anyOf(...)` (union, the `||` case),
`Classifier::allOf(...)` (intersection, the `&&` case), and `from(A::class, …)`
sugar. Re-implementing that with a hand-written boolean chain of `->matches()`
repeats the argument, can't be named or passed around, and drifts when a kind is
added.

Bad — the union is spelled out by hand:
    return DateTimeClassifier::make()->matches($type)
        || EnumClassifier::make()->matches($type);

Good — one named compound:
    return Classifier::anyOf(DateTimeClassifier::make(), EnumClassifier::make())->matches($type);
    // or: TypeClassifier::from(DateTimeClassifier::class, EnumClassifier::class)->matches($type);

`&&` becomes `allOf(...)` (the value must be ALL of the kinds).

WHAT FIRES — a `||` / `&&` chain of TWO OR MORE `->matches($x)` calls, all on the
SAME argument, whose receivers resolve to classes extending a `Classifier` base.

WHAT DOES NOT — a single `matches()`, calls on different arguments (not one
composable check), or receivers that are not classifiers (an unrelated `matches()`).
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $file = FileAst::of($ast);
        $finder = new NodeFinder;
        $base = (string) $this->config('classifier_base', 'Classifier');
        $warnings = [];

        $booleans = array_merge(
            $finder->findInstanceOf($ast, Expr\BinaryOp\BooleanOr::class),
            $finder->findInstanceOf($ast, Expr\BinaryOp\BooleanAnd::class),
        );

        foreach ($booleans as $bool) {
            if ($this->nestedInAnother($bool, $booleans)) {
                continue; // only the outermost node of a chain — one finding
            }

            $calls = [];

            foreach ($finder->findInstanceOf([$bool], Expr\MethodCall::class) as $call) {
                if ($call->name instanceof Node\Identifier
                    && $call->name->toString() === 'matches'
                    && ($call->args[0] ?? null) instanceof Node\Arg
                    && count($call->args) === 1
                    && $this->receiverIsClassifier($call->var, $file, $base)
                ) {
                    $calls[] = $call;
                }
            }

            if (count($calls) < 2 || ! $this->allSameArgument($calls)) {
                continue;
            }

            $isUnion = $bool instanceof Expr\BinaryOp\BooleanOr;
            $argument = $this->printer()->prettyPrintExpr($calls[0]->args[0]->value);
            $line = $calls[0]->getStartLine();

            $warnings[] = $this->warningAt(
                $line,
                sprintf(
                    '%d classifier `->matches(%s)` checks combined with `%s` — compose them into one named classifier: `Classifier::%s(…)->matches(%s)` (or `from(A::class, …)`), instead of a hand-written chain that repeats the argument and drifts as kinds are added.',
                    count($calls),
                    $argument,
                    $isUnion ? '||' : '&&',
                    $isUnion ? 'anyOf' : 'allOf',
                    $argument,
                ),
                $this->lineSnippet($content, $line),
                'classifier-compose:' . ($isUnion ? 'any' : 'all') . ':' . $argument,
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * Whether $receiver is a classifier instance — a `X::make()` / other static
     * call or `new X` whose class extends the configured Classifier base. A
     * chained call (`X::make()->and(...)`) recurses to its root receiver.
     */
    private function receiverIsClassifier(Expr $receiver, FileAst $file, string $base): bool
    {
        if ($receiver instanceof Expr\MethodCall) {
            return $this->receiverIsClassifier($receiver->var, $file, $base);
        }

        $class = match (true) {
            $receiver instanceof Expr\StaticCall && $receiver->class instanceof Node\Name => $receiver->class->toString(),
            $receiver instanceof Expr\New_ && $receiver->class instanceof Node\Name => $receiver->class->toString(),
            default => null,
        };

        if ($class === null) {
            return false;
        }

        return $this->extendsClassifierBase(ltrim($file->resolveType($class), '\\'), $base);
    }

    /** Structural: $fqcn itself, or an ancestor in its extends-chain, matches the base. */
    private function extendsClassifierBase(string $fqcn, string $base): bool
    {
        if ($this->matchesBase($fqcn, $base)) {
            return true;
        }

        $cursor = $fqcn;
        $depth = 0;

        while ($this->index !== null && $cursor !== null && $depth++ < 16) {
            $summary = $this->index->classByFqcn(ltrim($cursor, '\\'));

            if ($summary === null) {
                break;
            }

            if ($summary->parent !== null && $this->matchesBase(ltrim($summary->parent, '\\'), $base)) {
                return true;
            }

            $cursor = $summary->parent;
        }

        // Fallback when the type is not in the index (vendor, single-file run): the
        // conventional `<Kind>Classifier` name. Semantic ancestry above is primary;
        // this only fires when that signal is genuinely unreachable.
        return str_ends_with($this->shortOf($fqcn), $this->shortOf($base));
    }

    /** $base may be a short name ('Classifier') or an FQCN. */
    private function matchesBase(string $fqcn, string $base): bool
    {
        $base = ltrim($base, '\\');

        return $fqcn === $base || $this->shortOf($fqcn) === $this->shortOf($base);
    }

    /**
     * @param  list<Expr\MethodCall>  $calls
     */
    private function allSameArgument(array $calls): bool
    {
        $first = $this->printer()->prettyPrintExpr($calls[0]->args[0]->value);

        foreach ($calls as $call) {
            if ($this->printer()->prettyPrintExpr($call->args[0]->value) !== $first) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<Node>  $candidates
     */
    private function nestedInAnother(Node $node, array $candidates): bool
    {
        $start = (int) $node->getStartFilePos();
        $end = (int) $node->getEndFilePos();

        foreach ($candidates as $other) {
            if ($other === $node) {
                continue;
            }

            $oStart = (int) $other->getStartFilePos();
            $oEnd = (int) $other->getEndFilePos();

            if ($oStart <= $start && $oEnd >= $end && ($oStart < $start || $oEnd > $end)) {
                return true;
            }
        }

        return false;
    }

    private function shortOf(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

    private function printer(): PrettyPrinter\Standard
    {
        return $this->printer ??= new PrettyPrinter\Standard;
    }
}
