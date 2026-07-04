<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\Codebase;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter\Standard;

/** Resolves the constant-expression default for a Null Object (from default constructor or factory); yields null if no identity exists. */
final class NullObjectDefault
{
    public function __construct(private readonly Codebase $codebase) {}

    /**
     * The default expression that names $fqcn's Null Object, written to reference the type as
     * $asWritten (the name already in scope at the field — its own type token), or null when the
     * type has no identity we can express as a constant-expression default.
     */
    public function forType(?string $fqcn, string $asWritten): ?string
    {
        $class = $this->codebase->classNamed($fqcn);

        if ($class->constructorRequiresNoArguments()) {
            return "new {$asWritten}()";
        }

        return $class->node instanceof Class_ ? $this->inlineNullObjectFactory($class->node, $asWritten) : null;
    }

    /**
     * The inlined body of the type's sole Null Object factory, rendered against $written — or null
     * when there is no such factory, or more than one (ambiguous — which is the identity?).
     */
    private function inlineNullObjectFactory(Class_ $class, string $written): ?string
    {
        $factories = [];

        foreach ($class->getMethods() as $method) {
            $return = $this->nullObjectReturn($method, $class);

            if ($return !== null) {
                $factories[] = $return;
            }
        }

        return count($factories) === 1 ? $this->render($factories[0], $class, $written) : null;
    }

    /**
     * The `return <expr>` of a method that IS a Null Object factory — `public static`, no required
     * parameter, a single `return new self(<constant expression>)` whose constants are all this
     * class's own public ones. Null when the method is not that shape.
     */
    private function nullObjectReturn(ClassMethod $method, Class_ $class): ?Node
    {
        if (! $method->isStatic() || ! $method->isPublic() || $this->hasRequiredParam($method)) {
            return null;
        }

        $statements = $method->stmts ?? [];

        if (count($statements) !== 1 || ! $statements[0] instanceof Return_ || $statements[0]->expr === null) {
            return null;
        }

        $expr = $statements[0]->expr;

        return $this->isSelfConstruction($expr) && $this->isInlinableConstant($expr, $class) ? $expr : null;
    }

    private function hasRequiredParam(ClassMethod $method): bool
    {
        foreach ($method->params as $param) {
            if ($param->default === null && ! $param->variadic) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is $expr a `new self(...)` / `new static(...)` — an instance of the type itself?
     */
    private function isSelfConstruction(Node $expr): bool
    {
        return $expr instanceof New_
            && $expr->class instanceof Name
            && in_array($expr->class->toLowerString(), ['self', 'static'], true);
    }

    /**
     * Is $expr a constant expression PHP accepts as a default, referencing only scalars, this
     * class's own PUBLIC constants, and further `new self(...)` — nothing from another file that
     * might not be in scope where the default lands.
     */
    private function isInlinableConstant(Node $expr, Class_ $class): bool
    {
        if ($expr instanceof Scalar || ($expr instanceof ConstFetch && $expr->name instanceof Name)) {
            return true; // literals + true/false/null
        }

        if ($expr instanceof UnaryMinus) {
            return $this->isInlinableConstant($expr->expr, $class);
        }

        if ($expr instanceof ClassConstFetch) {
            return $expr->class instanceof Name
                && $expr->class->toLowerString() === 'self'
                && $expr->name instanceof Node\Identifier
                && $this->declaresPublicConst($class, $expr->name->toString());
        }

        if ($this->isSelfConstruction($expr)) {
            foreach ($expr->args as $arg) {
                if (! $arg instanceof Node\Arg || $arg->unpack || ! $this->isInlinableConstant($arg->value, $class)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    private function declaresPublicConst(Class_ $class, string $name): bool
    {
        foreach ($class->getConstants() as $const) {
            if (($const->flags & (Modifiers::PRIVATE | Modifiers::PROTECTED)) !== 0) {
                continue;
            }

            foreach ($const->consts as $declared) {
                if ($declared->name->toString() === $name) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Print $expr as a default expression, its `self`/`static` self-references rewritten to the
     * type as written at the call site (`new self(self::NOOP)` → `new Callback(Callback::NOOP)`).
     */
    private function render(Node $expr, Class_ $class, string $written): string
    {
        // Clone first (a shared subtree also lives in the value type's own AST — never mutate it),
        // then rewrite the self-references on the copy.
        $traverser = new NodeTraverser(
            new CloningVisitor(),
            new class($written) extends NodeVisitorAbstract {
                public function __construct(private readonly string $written) {}

                public function enterNode(Node $node): ?Node
                {
                    if ($node instanceof Name && in_array($node->toLowerString(), ['self', 'static'], true)) {
                        return new Name($this->written);
                    }

                    return null;
                }
            },
        );

        [$clone] = $traverser->traverse([$expr]);

        return new Standard()->prettyPrintExpr($clone);
    }
}
