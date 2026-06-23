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
use JesseGall\CodeCommandments\Support\CallGraph\NameResolver;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Flag a call-site `@var` that compensates for an under-annotated return type on
 * a PROJECT-OWNED callee (#76). A type should be declared once, where the value
 * is produced — on the method's `@return` — not re-asserted with a local `@var`
 * at every call site. A `@var Option<Widget> $x` over `$x = $registry->find(...)`
 * where `find()` only declares a bare `@return Option` is a band-aid: push the
 * generic to the source and every other caller gets it for free.
 *
 * Advisory. The LEAVE-WHENs are what make it trustworthy: the imprecision is only
 * the source's to fix when it is NOT injected by a fallback argument, a vendor
 * boundary, or caller-only knowledge.
 */
#[IntroducedIn('1.128.0')]
class PushGenericToSourceProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    private ?CodebaseIndex $index = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    public function description(): string
    {
        return 'Push a type to its source @return instead of re-asserting it with a call-site @var';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A local `@var T $x` sits over `$x = $obj->method(...)` where `method()` is PROJECT-OWNED and declares a looser `@return` (a bare generic class with no `<…>`, `mixed`, or `array` with no value type). The annotation duplicates a type that belongs on the callee.')
            ->leaveWhen('the result is widened by an argument (`->getOr($default)`, `?? $default`) so no `@return` change can remove the `@var`; the callee is third-party/vendor (unannotatable); the caller legitimately knows more (a downcast after a runtime check); or the `@var` is not over a single project call.')
            ->whenUnsure('ask: can the imprecision be fixed by editing ONE callee\'s `@return`? If yes, move the generic there and drop the `@var`. If it comes from a fallback, a vendor boundary, or caller-only knowledge, keep the `@var`.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A local `@var` over a project-owned call is usually a confession that the
callee's `@return` is missing its generic. Declare the type once, at the source,
and every caller infers it — a call-site `@var` is a second copy that drifts.

Bad — the source is under-annotated, the caller compensates:
    final class WidgetRegistry
    {
        /** @return Option */                      // bare generic, no <T>
        public function find(string $key): Option { … }
    }
    // caller:
    /** @var Option<Widget> $found */              // band-aid
    $found = $this->registry->find($key);

Good — push the generic to the source, drop the `@var`:
    /** @return Option<Widget> */                  // declared once
    public function find(string $key): Option { … }
    // caller:
    $found = $this->registry->find($key);          // inferred

WHAT FIRES — a `@var T $x` directly over `$x = <call>;` where the WHOLE
right-hand side is a single call to a project-owned method (resolved via the
index), and the callee's declared return is strictly wider than `T`: the same
generic class but unparameterized, `array`/`iterable` with no value type, or
`mixed`.

WHAT DOES NOT — a `@var` over a call that is then WIDENED by an argument
(`->getOr($default)`, `?? $default`: the source is already correct, the union
comes from the fallback); a vendor/framework callee you cannot annotate; a
downcast the callee cannot see; or a `@var` that is not over a single call. This
is advisory — when the type can be fixed at one `@return`, do that; otherwise the
`@var` is the right tool.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->index === null) {
            return $this->righteous();
        }

        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $namespace = $this->fileNamespace($ast);
        $uses = $this->fileUses($ast);
        $warnings = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Expression::class) as $stmt) {
            if (! $stmt->expr instanceof Expr\Assign) {
                continue;
            }

            $varType = $this->varAnnotationType($stmt);

            if ($varType === null) {
                continue;
            }

            // The `@var T $name` must annotate THE ASSIGNED variable — a docblock
            // that names a different variable (e.g. a `@var` for the enclosing
            // foreach's loop var that happens to sit above this assignment) is not
            // about this call. A nameless `@var T` is taken to apply to it.
            $target = $stmt->expr->var;

            if ($varType['var'] !== null
                && (! $target instanceof Expr\Variable || ! is_string($target->name) || $target->name !== $varType['var'])
            ) {
                continue;
            }

            // The WHOLE RHS must be a single direct call — a chained `->getOr()`
            // / `?? $default` is fallback-widening (LEAVE-WHEN), not the call's
            // own type.
            $call = $stmt->expr->expr;

            if (! $call instanceof Expr\MethodCall && ! $call instanceof Expr\StaticCall) {
                continue;
            }

            $callee = $this->resolveCallee($call, $ast, $uses, $namespace, $stmt);

            if ($callee === null) {
                continue;
            }

            if (! $this->calleeIsUnderAnnotated($callee, $varType)) {
                continue;
            }

            $line = $stmt->getStartLine();
            $warnings[] = $this->warningAt(
                $line,
                sprintf('This `@var %s` compensates for an under-annotated return type — add the generic to `%s::%s()`\'s `@return` and remove the annotation, so every caller infers it.', $varType['raw'], $this->shortName($callee->classFqcn), $callee->name),
                $this->lineSnippet($content, $line),
                'push-generic:' . $callee->name,
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * The `@var T $x` type written above (or on) an assignment statement, as
     * `['raw' => 'Option<Widget>', 'base' => 'option', 'parameterized' => true]`,
     * or null when there is no `@var`.
     *
     * @return array{raw: string, base: string, parameterized: bool, var: ?string}|null
     */
    private function varAnnotationType(Node\Stmt\Expression $stmt): ?array
    {
        $doc = $stmt->getDocComment();

        if ($doc === null) {
            return null;
        }

        // `@var <type> [$name]` — capture the type and, when present, the variable
        // it annotates (so it can be matched to the assignment target).
        if (preg_match('/@var\s+([^\s$]+)(?:\s+\$(\w+))?/', $doc->getText(), $m) !== 1) {
            return null;
        }

        $raw = $m[1];
        $base = $raw;
        $parameterized = false;

        if (preg_match('/^([\\\\\w]+)\s*<.+>$/', $raw, $mm) === 1) {
            $base = $mm[1];
            $parameterized = true;
        }

        return [
            'raw' => $raw,
            'base' => strtolower($this->shortName($base)),
            'parameterized' => $parameterized,
            'var' => ($m[2] ?? '') !== '' ? $m[2] : null,
        ];
    }

    /**
     * The project-owned method summary the call resolves to, or null (vendor /
     * unresolved receiver / unknown class — all LEAVE-WHENs).
     *
     * @param  array<Node>  $ast
     * @param  array<string, string>  $uses
     */
    private function resolveCallee(Expr $call, array $ast, array $uses, ?string $namespace, Node $context)
    {
        if ($this->index === null) {
            return null;
        }

        $classFqcn = null;
        $method = null;

        if ($call instanceof Expr\MethodCall && $call->name instanceof Node\Identifier) {
            $method = $call->name->toString();
            $classFqcn = $this->receiverType($call->var, $ast, $uses, $namespace, $context);
        } elseif ($call instanceof Expr\StaticCall && $call->name instanceof Node\Identifier && $call->class instanceof Node\Name) {
            $method = $call->name->toString();
            $classFqcn = ltrim(NameResolver::resolve($call->class->toString(), $uses, $namespace), '\\');
        }

        if ($classFqcn === null || $method === null) {
            return null;
        }

        $summary = $this->index->classByFqcn($classFqcn);

        return $summary?->methods[$method] ?? null;
    }

    /**
     * The resolved FQCN of a call receiver — a typed param `$x`, or a typed
     * `$this->prop` — else null (an unresolved or chained receiver is a LEAVE).
     *
     * @param  array<Node>  $ast
     * @param  array<string, string>  $uses
     */
    private function receiverType(Expr $recv, array $ast, array $uses, ?string $namespace, Node $context): ?string
    {
        if ($recv instanceof Expr\Variable && is_string($recv->name)) {
            $type = $this->paramTypeInScope($recv->name, $context, $ast);

            return $type !== null ? ltrim(NameResolver::resolve($type, $uses, $namespace), '\\') : null;
        }

        if ($recv instanceof Expr\PropertyFetch
            && $recv->var instanceof Expr\Variable && $recv->var->name === 'this'
            && $recv->name instanceof Node\Identifier
        ) {
            $class = $this->enclosingClass($context, $ast);
            $type = $class !== null ? $this->propertyType($class, $recv->name->toString()) : null;

            return $type !== null ? ltrim(NameResolver::resolve($type, $uses, $namespace), '\\') : null;
        }

        return null;
    }

    private function calleeIsUnderAnnotated($callee, array $varType): bool
    {
        $return = $callee->returnTypeName;

        // mixed return + a specific @var → the source says nothing; push it.
        if ($return === 'mixed' && $varType['base'] !== 'mixed') {
            return true;
        }

        // A parameterized @var (`Option<Widget>`, `array<…>`, `Collection<…>`)
        // whose base matches the callee's return, where the callee's @return is
        // NOT parameterized → the source is missing the generic.
        if (! $varType['parameterized'] || $callee->returnDocIsParameterized) {
            return false;
        }

        if ($return === null) {
            return false;
        }

        // array/iterable return with a parameterized @var array/collection.
        if (in_array($return, ['array', 'iterable'], true)
            && in_array($varType['base'], ['array', 'iterable', 'collection', 'list'], true)
        ) {
            return true;
        }

        // Same generic class, unparameterized at the source.
        return strtolower($return) === $varType['base'];
    }

    /**
     * @param  array<Node>  $ast
     */
    private function paramTypeInScope(string $name, Node $context, array $ast): ?string
    {
        $pos = (int) $context->getStartFilePos();
        $finder = new NodeFinder;
        $best = null;
        $bestStart = -1;

        foreach (array_merge(
            $finder->findInstanceOf($ast, Node\Stmt\ClassMethod::class),
            $finder->findInstanceOf($ast, Node\Stmt\Function_::class),
            $finder->findInstanceOf($ast, Expr\Closure::class),
        ) as $fn) {
            $start = (int) $fn->getStartFilePos();

            if ($start > $pos || (int) $fn->getEndFilePos() < $pos || $start <= $bestStart) {
                continue;
            }

            foreach ($fn->params as $param) {
                if ($param->var instanceof Expr\Variable && $param->var->name === $name
                    && $param->type instanceof Node\Name
                ) {
                    $best = $param->type->toString();
                    $bestStart = $start;
                }
            }
        }

        return $best;
    }

    /**
     * @param  array<Node>  $ast
     */
    private function enclosingClass(Node $node, array $ast): ?Node\Stmt\Class_
    {
        $pos = (int) $node->getStartFilePos();
        $best = null;
        $bestStart = -1;

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            $start = (int) $class->getStartFilePos();

            if ($start <= $pos && (int) $class->getEndFilePos() >= $pos && $start > $bestStart) {
                $best = $class;
                $bestStart = $start;
            }
        }

        return $best;
    }

    private function propertyType(Node\Stmt\Class_ $class, string $property): ?string
    {
        foreach ($class->getProperties() as $prop) {
            foreach ($prop->props as $declared) {
                if ($declared->name->toString() === $property && $prop->type instanceof Node\Name) {
                    return $prop->type->toString();
                }
            }
        }

        $ctor = $class->getMethod('__construct');

        if ($ctor !== null) {
            foreach ($ctor->params as $param) {
                if ($param->flags !== 0 && $param->var instanceof Expr\Variable
                    && $param->var->name === $property && $param->type instanceof Node\Name
                ) {
                    return $param->type->toString();
                }
            }
        }

        return null;
    }

    private function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }


    /**
     * @param  array<Node>  $ast
     */
    private function fileNamespace(array $ast): ?string
    {
        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Namespace_::class) as $ns) {
            return $ns->name?->toString();
        }

        return null;
    }

    /**
     * @param  array<Node>  $ast
     * @return array<string, string>
     */
    private function fileUses(array $ast): array
    {
        $uses = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Use_::class) as $use) {
            foreach ($use->uses as $u) {
                $uses[$u->getAlias()->toString()] = $u->name->toString();
            }
        }

        return $uses;
    }
}
