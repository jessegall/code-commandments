<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Flag `new ValueBag($v ?? [])` / `new ValueBag(T_Array::coalesce($v))` — a value
 * object built from a nullable / shape-guarded value with the null-handling and
 * `@var` shape assertion inline at the call site. Suggest a total
 * `ValueBag::coalesce($value)` factory that owns that logic once.
 *
 * The value-object analogue of PreferTypeCoalesce (#106, the T_* scalars) and the
 * producer side of PreferTotalOverNullable (#108).
 */
#[IntroducedIn('1.145.0')]
class PreferCoalesceFactoryProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    /** Base-class markers that make a class a coalescible value object. */
    private const VALUE_BASES = ['Fluent', 'Data', 'Collection'];

    private ?CodebaseIndex $index = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    public function description(): string
    {
        return 'Hoist new ValueObject($nullableOrLoose) ceremony into a total ::coalesce() factory';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A value object / Fluent bag / collection is constructed from a null-coalescing or shape-guarding argument inline — `new Bag($v ?? [])`, `new Bag(T_Array::coalesce($v))`, `new Bag(is_array($v) ? $v : [])`. The null-handling (and the `@var` shape assertion) is repeated at the call site.')
            ->leaveWhen('the value is already correctly typed (no `??` / shape guard) — `new Bag($alreadyArray)` has no ceremony to hoist; or the class is not a value object (a service/handler taking a nullable array config is not this smell).')
            ->whenUnsure('add a total `static coalesce(mixed $value): static` factory on the class that does the null/shape handling once (`is_array($value) ? $value : T_Array::empty()`), and replace call sites with `Bag::coalesce($v)`.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Constructing a value object from a nullable or loosely-typed value spreads the
same null-guard and shape assertion across every call site. A total static
`::coalesce()` factory owns that logic once, so callers read as one expression.

Bad — inline null-guard + shape assertion at the call site (repeated):
    /** @var array<string, mixed> $snapshot */
    $snapshot = T_Array::coalesce($run->context_snapshot);
    $bag = new ValueBag($snapshot);

    $bag = new ValueBag($value ?? []);
    $bag = new ValueBag(is_array($value) ? $value : []);

Good — one total factory; call sites are clean:
    final class ValueBag extends Fluent
    {
        public static function coalesce(mixed $value): self
        {
            /** @var array<string, mixed> $attributes */
            $attributes = is_array($value) ? $value : T_Array::empty();

            return new self($attributes);   // the shape assertion lives here, once
        }
    }

    $bag = ValueBag::coalesce($run->context_snapshot);

It also resolves a recurring PHPStan papercut: `new Fluent($jsonDecodedArray)` fails
max level because a decoded array is `array<array-key, mixed>`, not
`array<string, mixed>`. The `coalesce()` factory is the one place the
`@var array<string, mixed>` assertion belongs.

WHAT FIRES — `new X(<arg>)` (or `X::make(<arg>)`) where X is a value object (a
Fluent / Collection / Data subclass, resolved in-file or via the index) and the
argument is a coalescing / shape guard: `$v ?? []`, `$v ?? T_*::EMPTY`,
`T_*::coalesce($v)`, or `is_array($v) ? $v : []`.

WHAT DOES NOT — construction from an already-typed value (no guard), or a class
that is not a value object. Advisory: adding the factory is a design call.
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

        foreach ($finder->findInstanceOf($ast, Expr\New_::class) as $new) {
            if (! $new->class instanceof Node\Name) {
                continue;
            }

            $this->inspect($new->class, $new->getArgs(), $new->getStartLine(), $content, $ast, $finder, $warnings);
        }

        // X::make(<arg>) — the named-constructor twin.
        foreach ($finder->findInstanceOf($ast, Expr\StaticCall::class) as $call) {
            if ($call->class instanceof Node\Name
                && $call->name instanceof Node\Identifier
                && strtolower($call->name->toString()) === 'make'
            ) {
                $this->inspect($call->class, $call->getArgs(), $call->getStartLine(), $content, $ast, $finder, $warnings);
            }
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * @param  list<Node\Arg>  $args
     * @param  array<Node>  $ast
     * @param  list<Warning>  $warnings
     */
    private function inspect(Node\Name $class, array $args, int $line, string $content, array $ast, NodeFinder $finder, array &$warnings): void
    {
        if (count($args) !== 1 || $args[0]->unpack || ! $this->isCoalescingArg($args[0]->value)) {
            return;
        }

        $short = $class->getLast();

        if (in_array(strtolower($short), ['self', 'static', 'parent'], true) || ! $this->isValueObject($class, $short, $ast, $finder)) {
            return;
        }

        $warnings[] = $this->warningAt(
            $line,
            sprintf('`%s` is built from a null-coalescing / shape-guarded value inline — add a total `%s::coalesce($value)` factory that owns the null/shape handling once, and call `%s::coalesce($v)`.', $short, $short, $short),
            $this->lineAt($content, $line),
            'coalesce-factory:' . $short,
        );
    }

    /**
     * Whether $arg is a null-coalescing / shape-guard construction argument:
     * `$v ?? <empty>`, `T_*::coalesce(...)`, or `is_array($v) ? $v : <empty>`.
     */
    private function isCoalescingArg(Expr $arg): bool
    {
        if ($arg instanceof Expr\BinaryOp\Coalesce) {
            return $this->isEmptyish($arg->right);
        }

        if ($arg instanceof Expr\StaticCall
            && $arg->class instanceof Node\Name
            && $arg->name instanceof Node\Identifier
            && strtolower($arg->name->toString()) === 'coalesce'
            && str_starts_with($arg->class->getLast(), 'T_')
        ) {
            return true;
        }

        if ($arg instanceof Expr\Ternary
            && $arg->else instanceof Expr
            && $this->isEmptyish($arg->else)
            && $arg->cond instanceof Expr\FuncCall
            && $arg->cond->name instanceof Node\Name
            && in_array(strtolower($arg->cond->name->toString()), ['is_array', 'is_string', 'is_int', 'is_iterable'], true)
        ) {
            return true;
        }

        return false;
    }

    /**
     * An "empty" default literal: `[]`/`''`/`0`/`0.0`/`false` or a
     * `T_*::EMPTY`/`ZERO`/`FALSE` constant.
     */
    private function isEmptyish(Expr $expr): bool
    {
        if ($expr instanceof Expr\Array_) {
            return $expr->items === [];
        }

        if ($expr instanceof Node\Scalar\String_) {
            return $expr->value === '';
        }

        if ($expr instanceof Node\Scalar\Int_) {
            return $expr->value === 0;
        }

        if ($expr instanceof Node\Scalar\Float_) {
            return $expr->value === 0.0;
        }

        if ($expr instanceof Expr\ConstFetch) {
            return strtolower($expr->name->toString()) === 'false';
        }

        if ($expr instanceof Expr\ClassConstFetch
            && $expr->class instanceof Node\Name
            && $expr->name instanceof Node\Identifier
        ) {
            return str_starts_with($expr->class->getLast(), 'T_')
                && in_array($expr->name->toString(), ['EMPTY', 'ZERO', 'FALSE'], true);
        }

        return false;
    }

    /**
     * Whether $short is a value object — a class extending a Fluent / Collection /
     * Data base, resolved from this file or the codebase index.
     *
     * @param  array<Node>  $ast
     */
    private function isValueObject(Node\Name $class, string $short, array $ast, NodeFinder $finder): bool
    {
        $node = $this->findClassNode($class, $short, $ast, $finder);

        if ($node === null || ! $node->extends instanceof Node\Name) {
            return false;
        }

        $parent = $node->extends->getLast();

        foreach (self::VALUE_BASES as $base) {
            if ($parent === $base || str_ends_with($parent, $base)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<Node>  $ast
     */
    private function findClassNode(Node\Name $class, string $short, array $ast, NodeFinder $finder): ?Node\Stmt\Class_
    {
        foreach ($finder->findInstanceOf($ast, Node\Stmt\Class_::class) as $node) {
            if ($node->name?->toString() === $short) {
                return $node;
            }
        }

        $resolved = $class->getAttribute('resolvedName');
        $fqcn = $resolved instanceof Node\Name ? $resolved->toString() : ($class->isFullyQualified() ? ltrim($class->toString(), '\\') : null);

        if ($fqcn === null || $this->index === null) {
            return null;
        }

        $summary = $this->index->classByFqcn(ltrim($fqcn, '\\'));

        if ($summary === null) {
            return null;
        }

        $fileContent = @file_get_contents($summary->filePath);

        if (! is_string($fileContent)) {
            return null;
        }

        $fileAst = $this->parse($fileContent);

        if ($fileAst === null) {
            return null;
        }

        foreach ((new NodeFinder)->findInstanceOf($fileAst, Node\Stmt\Class_::class) as $node) {
            if ($node->name?->toString() === $short) {
                return $node;
            }
        }

        return null;
    }

    private function lineAt(string $content, int $line): string
    {
        $lines = explode("\n", $content);

        return isset($lines[$line - 1]) ? trim($lines[$line - 1]) : '';
    }
}
