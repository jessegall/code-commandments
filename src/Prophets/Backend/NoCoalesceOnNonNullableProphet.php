<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\Resolvers\Ast\FileAst;
use JesseGall\CodeCommandments\Support\Resolvers\Ast\ReceiverTypeResolver;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Flag `<expr> ?? <default>` (and `(cast)(<expr> ?? <default>)`) where <expr> is provably NEVER NULL — a non-nullable typed parameter, or an always-initialized non-nullable property. The `??` is dead: the default can never be reached, which hides a misunderstanding of the value's type.
 */
#[IntroducedIn('2.72.0')]
class NoCoalesceOnNonNullableProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    private ?CodebaseIndex $index = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    public function description(): string
    {
        return 'Do not `??`-coalesce a value that is never null — the default is dead';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Correctness;
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
`<expr> ?? <default>` only reaches the default when <expr> is null (or unset).
When <expr> is PROVABLY never null — a non-nullable typed parameter, or an
always-initialized non-nullable property — the `??` is dead code: the default can
never run. It reads as "this might be null" when the type says it never is, which
hides a misunderstanding (or a stale nullable that has since been tightened).

Bad — coalescing a non-nullable value:
    public function f(int $version): int
    {
        return $version ?? 1;            // $version is `int`, never null — `?? 1` is dead
    }
    $stored = (int) ($workflow->version ?? 1);   // same, hidden inside a cast

Good — trust the type:
    return $version;
    $stored = $workflow->version;

WHAT FIRES — `<expr> ?? <default>` (including inside a numeric/string cast) where
<expr> resolves to a NON-NULLABLE type: a typed parameter, a `$this->` property,
or an object property (through the codebase index) that is non-nullable AND always
initialized (a promoted constructor parameter, or a declared property with a
default).

WHAT DOES NOT — a nullable (`?T` / `T|null`) or `mixed` value (the `??` is real); a
declared typed property with NO default (it may be uninitialized, where `??` is a
legitimate init guard); or an unresolved expression (unknown nullability).
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
        $sins = [];

        foreach ($finder->findInstanceOf($ast, Node\Expr\BinaryOp\Coalesce::class) as $coalesce) {
            if (! $this->isNeverNull($coalesce->left, $coalesce, $file)) {
                continue;
            }

            $line = $coalesce->getStartLine();
            $sins[] = $this->sinAt(
                $line,
                sprintf(
                    'Coalescing `%s`, which is never null — the `?? %s` default is dead. Drop the `??` and trust the type.',
                    $this->srcOf($coalesce->left, $content),
                    $this->srcOf($coalesce->right, $content),
                ),
                $this->lineSnippet($content, $line),
                'coalesce-non-nullable:' . $coalesce->left->getStartLine(),
            );
        }

        return $sins === [] ? $this->righteous() : $this->fallen($sins);
    }

    /** Whether $expr resolves to a non-nullable, always-present value. */
    private function isNeverNull(Node\Expr $expr, Node $context, FileAst $file): bool
    {
        // A typed parameter — always bound, so a non-nullable type is never null.
        if ($expr instanceof Node\Expr\Variable && is_string($expr->name)) {
            $type = ReceiverTypeResolver::paramTypeNode($expr->name, $context, $file->nodes);

            return $type !== null && ! $this->permitsNull($type);
        }

        if (($expr instanceof Node\Expr\PropertyFetch || $expr instanceof Node\Expr\NullsafePropertyFetch)
            && $expr->name instanceof Node\Identifier
            && $expr->var instanceof Node\Expr\Variable
        ) {
            // $this->prop — the enclosing class's own property.
            if ($expr->var->name === 'this') {
                $class = ReceiverTypeResolver::enclosingClass($context, $file->nodes);

                return $class !== null && $this->propertyNeverNull($class, $expr->name->toString());
            }

            // $obj->prop — resolve $obj's class through the index.
            if (is_string($expr->var->name)) {
                return $this->objectPropertyNeverNull($expr->var->name, $expr->name->toString(), $context, $file);
            }
        }

        return false;
    }

    /** A non-nullable property that is always initialized (promoted ctor param, or has a default). */
    private function propertyNeverNull(Node\Stmt\Class_ $class, string $property): bool
    {
        $type = ReceiverTypeResolver::propertyTypeNode($class, $property);

        if ($type === null || $this->permitsNull($type)) {
            return false;
        }

        $ctor = $class->getMethod('__construct');

        if ($ctor !== null) {
            foreach ($ctor->params as $param) {
                if ($param->flags !== 0 && $param->var instanceof Node\Expr\Variable && $param->var->name === $property) {
                    return true; // promoted constructor parameter — always set
                }
            }
        }

        foreach ($class->getProperties() as $prop) {
            foreach ($prop->props as $declared) {
                if ($declared->name->toString() === $property) {
                    return $declared->default !== null; // declared with a default — always set
                }
            }
        }

        return false;
    }

    private function objectPropertyNeverNull(string $objVar, string $property, Node $context, FileAst $file): bool
    {
        if ($this->index === null) {
            return false;
        }

        $type = ReceiverTypeResolver::paramTypeNode($objVar, $context, $file->nodes);
        $name = $type instanceof Node\Name
            ? $type
            : ($type instanceof Node\NullableType && $type->type instanceof Node\Name ? $type->type : null);

        if ($name === null) {
            return false;
        }

        $summary = $this->index->classByFqcn(ltrim($file->resolveType($name->toString()), '\\'));

        if ($summary === null) {
            return false;
        }

        $content = @file_get_contents($summary->filePath);

        if (! is_string($content)) {
            return false;
        }

        $classAst = $this->parse($content);

        if ($classAst === null) {
            return false;
        }

        foreach ((new NodeFinder)->findInstanceOf($classAst, Node\Stmt\Class_::class) as $class) {
            if ($class->name?->toString() === $name->getLast()) {
                return $this->propertyNeverNull($class, $property);
            }
        }

        return false;
    }

    /** Whether the declared type $type can hold null (`?T`, `T|null`, `mixed`, `null`). */
    private function permitsNull(Node $type): bool
    {
        if ($type instanceof Node\NullableType) {
            return true;
        }

        if ($type instanceof Node\Identifier) {
            $name = strtolower($type->toString());

            return $name === 'mixed' || $name === 'null';
        }

        if ($type instanceof Node\UnionType) {
            foreach ($type->types as $member) {
                if ($this->permitsNull($member)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function srcOf(Node $node, string $content): string
    {
        return substr($content, (int) $node->getStartFilePos(), (int) $node->getEndFilePos() - (int) $node->getStartFilePos() + 1);
    }
}
