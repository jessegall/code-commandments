<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Support\Resolvers\Ast\ReceiverTypeResolver;
use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use PhpParser\Node;
use PhpParser\NodeFinder;

/**
 * Flag `<nullable expr> ?? <type's empty literal>` and suggest the typed,
 * named php-types helper `T_X::coalesce(<expr>)` — the whole `T_*` family:
 * `?? []`/`T_Array::EMPTY` → T_Array, `?? ''`/`T_String::EMPTY` → T_String,
 * `?? 0`/`T_Int::ZERO` → T_Int, `?? 0.0`/`T_Float::ZERO` → T_Float,
 * `?? false`/`T_Bool::FALSE` → T_Bool.
 *
 * Only fires when the left side is a NULLABLE of the matching type (a `?array`
 * / `?string` / … variable, $this property, or resolved object property) — so a
 * `mixed`/untyped `?? []` is never touched and the coercion is sound.
 */
#[IntroducedIn('1.140.0')]
class PreferTypeCoalesceProphet extends PhpCommandment implements SinRepenter, NeedsCodebaseIndex
{
    /** builtin type => php-types wrapper short name. */
    private const WRAPPERS = [
        'array' => 'T_Array',
        'string' => 'T_String',
        'int' => 'T_Int',
        'float' => 'T_Float',
        'bool' => 'T_Bool',
    ];

    /** cast node => builtin type. `(int)(… ?? …)` IS `T_Int::coalesce(…, …)`. */
    private const CAST_TYPES = [
        Node\Expr\Cast\Array_::class => 'array',
        Node\Expr\Cast\String_::class => 'string',
        Node\Expr\Cast\Int_::class => 'int',
        Node\Expr\Cast\Double::class => 'float',
        Node\Expr\Cast\Bool_::class => 'bool',
    ];

    private ?CodebaseIndex $index = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    public function description(): string
    {
        return 'Prefer T_*::coalesce() over `?? <empty literal>` on a nullable typed value';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A nullable typed value is defaulted with `?? <empty literal>` (`?? []`, `?? \'\'`, `?? 0`, `?? false`, or a `T_*::EMPTY`/`ZERO`/`FALSE` constant). php-types ships the named, typed `T_X::coalesce($value)` for exactly this.')
            ->leaveWhen('the left side is NOT a resolvable nullable of the matching type (a `mixed`/untyped expression, an object property whose type cannot be resolved) — those are deliberately not flagged. A `foreach`-guard `?? []` inline is also fine to leave.')
            ->whenUnsure('use `T_X::coalesce($value)` for value positions (assignment, argument, count(...), constructor arg); a bare `?? []` reads fine only as an inline foreach guard.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
php-types ships a typed, named coalescing helper for every scalar/array wrapper:
`T_Array::coalesce(?array): array`, `T_String::coalesce(mixed): string`,
`T_Int::coalesce(mixed): int`, `T_Float::coalesce(mixed): float`,
`T_Bool::coalesce(mixed): bool`. They say "this nullable value, or its type's
empty/zero/false" in one place — but the raw `?? <empty literal>` idiom hides
them.

Bad — raw empty-literal coalesce on a nullable typed value:
    $steps   = $run->steps ?? [];
    $steps   = $run->steps ?? T_Array::EMPTY;
    $name    = $this->label ?? '';
    $limit   = $config->limit ?? 0;

Good — the typed helper:
    $steps = T_Array::coalesce($run->steps);
    $name  = T_String::coalesce($this->label);
    $limit = T_Int::coalesce($config->limit);

There is also the CAST form — `(int) (<expr> ?? <default>)` — which is the inline
body of the helper itself (`T_Int::coalesce` IS `(int) ($value ?? $default)`), so
it is sound for ANY default and needs no type resolution:
    $n = (int) ($req->page ?? 1);   ->   $n = T_Int::coalesce($req->page, 1);
    $n = (int) ($req->page ?? 0);   ->   $n = T_Int::coalesce($req->page);

WHAT FIRES — (1) `<expr> ?? <empty literal>` where `<expr>` resolves to a NULLABLE
of the literal's type (a `?array`/`?string`/`?int`/`?float`/`?bool` parameter,
`$this` property, or index-resolved object property); the empty literal is
`[]`/`''`/`0`/`0.0`/`false` or the matching `T_*::EMPTY`/`ZERO`/`FALSE`. (2) a cast
`(int|string|float|bool|array) (<expr> ?? <default>)` for ANY default — the cast
fixes the type.

WHAT DOES NOT — a bare `<expr> ?? <non-empty default>` with no cast (the type is
unknown without the cast), a `mixed`/untyped left side, or a non-nullable value
(the `??` is already dead — that is NoCoalesceOnNonNullable's smell).

AUTO-FIXABLE: `repent` rewrites both forms to `T_X::coalesce(<expr>[, <default>])`.
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

        // `(int)(<expr> ?? <default>)` IS `T_Int::coalesce(<expr>, <default>)` —
        // the cast makes the type explicit, so it is sound for ANY default.
        $castCoalesces = $this->castCoalesces($ast, $finder);
        $handled = [];

        foreach ($castCoalesces as $cc) {
            $handled[spl_object_id($cc['coalesce'])] = true;
            $wrapper = self::WRAPPERS[$cc['type']];

            $warnings[] = $this->warningAt(
                $cc['cast']->getStartLine(),
                sprintf(
                    '`(%s) (… ?? …)` hand-casts a coalesced value — use `%s::coalesce(<value>, <default>)`, which IS exactly `(%s) (<value> ?? <default>)`.',
                    $cc['type'],
                    $wrapper,
                    $cc['type'],
                ),
                $this->lineSnippet($content, $cc['cast']->getStartLine()),
                'cast-coalesce:' . $cc['type'],
                true,
            );
        }

        foreach ($finder->findInstanceOf($ast, Node\Expr\BinaryOp\Coalesce::class) as $coalesce) {
            if (isset($handled[spl_object_id($coalesce)])) {
                continue; // the enclosing cast already covers this coalesce
            }

            $type = $this->matchedType($coalesce, $ast, $finder);

            if ($type === null) {
                continue;
            }

            $wrapper = self::WRAPPERS[$type];

            $warnings[] = $this->warningAt(
                $coalesce->getStartLine(),
                sprintf(
                    '`?? %s` defaults a nullable %s by hand — use the typed helper `%s::coalesce(...)` instead.',
                    $this->describeRight($coalesce->right),
                    $type,
                    $wrapper,
                ),
                $this->lineSnippet($content, $coalesce->getStartLine()),
                'type-coalesce:' . $type,
                true,
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
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

        $ast = $this->parse($content);

        if ($ast === null) {
            return RepentanceResult::unrepentant('Unable to parse PHP file');
        }

        $finder = new NodeFinder;

        /** @var list<array{start: int, end: int, text: string}> $edits */
        $edits = [];
        $penance = [];
        $handled = [];

        foreach ($this->castCoalesces($ast, $finder) as $cc) {
            $handled[spl_object_id($cc['coalesce'])] = true;

            $edits[] = [
                'start' => (int) $cc['cast']->getStartFilePos(),
                'end' => (int) $cc['cast']->getEndFilePos(),
                'text' => $this->coalesceCallText($cc['type'], $cc['coalesce'], $content),
            ];
            $penance[] = sprintf('Rewrote `(%s) (… ?? …)` to %s::coalesce()', $cc['type'], self::WRAPPERS[$cc['type']]);
        }

        foreach ($finder->findInstanceOf($ast, Node\Expr\BinaryOp\Coalesce::class) as $coalesce) {
            if (isset($handled[spl_object_id($coalesce)])) {
                continue;
            }

            $type = $this->matchedType($coalesce, $ast, $finder);

            if ($type === null) {
                continue;
            }

            $left = $coalesce->left;
            $leftSrc = substr($content, (int) $left->getStartFilePos(), (int) $left->getEndFilePos() - (int) $left->getStartFilePos() + 1);

            $edits[] = [
                'start' => (int) $coalesce->getStartFilePos(),
                'end' => (int) $coalesce->getEndFilePos(),
                'text' => sprintf('%s::coalesce(%s)', self::WRAPPERS[$type], $leftSrc),
            ];
            $penance[] = sprintf('Rewrote `?? <empty>` to %s::coalesce()', self::WRAPPERS[$type]);
        }

        if ($edits === []) {
            return RepentanceResult::unchanged();
        }

        usort($edits, static fn (array $a, array $b): int => $b['start'] <=> $a['start']);

        foreach ($edits as $edit) {
            $content = substr($content, 0, $edit['start']) . $edit['text'] . substr($content, $edit['end'] + 1);
        }

        return RepentanceResult::absolved($content, $penance);
    }

    /**
     * Every `(int|string|float|bool|array)(<expr> ?? <default>)` in $ast — a cast
     * directly wrapping a coalesce, which is the inline form of `T_X::coalesce`.
     *
     * @param  array<Node>  $ast
     * @return list<array{cast: Node\Expr\Cast, type: string, coalesce: Node\Expr\BinaryOp\Coalesce}>
     */
    private function castCoalesces(array $ast, NodeFinder $finder): array
    {
        $out = [];

        foreach ($finder->findInstanceOf($ast, Node\Expr\Cast::class) as $cast) {
            $type = self::CAST_TYPES[$cast::class] ?? null;

            if ($type !== null && $cast->expr instanceof Node\Expr\BinaryOp\Coalesce) {
                $out[] = ['cast' => $cast, 'type' => $type, 'coalesce' => $cast->expr];
            }
        }

        return $out;
    }

    /** `T_X::coalesce(<value>)`, or `T_X::coalesce(<value>, <default>)` when the default is not the type's empty. */
    private function coalesceCallText(string $type, Node\Expr\BinaryOp\Coalesce $coalesce, string $content): string
    {
        $wrapper = self::WRAPPERS[$type];
        $value = $this->srcOf($coalesce->left, $content);

        if ($this->emptyLiteralType($coalesce->right) === $type) {
            return sprintf('%s::coalesce(%s)', $wrapper, $value);
        }

        return sprintf('%s::coalesce(%s, %s)', $wrapper, $value, $this->srcOf($coalesce->right, $content));
    }

    private function srcOf(Node $node, string $content): string
    {
        return substr($content, (int) $node->getStartFilePos(), (int) $node->getEndFilePos() - (int) $node->getStartFilePos() + 1);
    }

    /**
     * The builtin type when this `??` is `<nullable expr> ?? <type's empty
     * literal>` and both sides agree on the type; else null.
     *
     * @param  array<Node>  $ast
     */
    private function matchedType(Node\Expr\BinaryOp\Coalesce $coalesce, array $ast, NodeFinder $finder): ?string
    {
        $rightType = $this->emptyLiteralType($coalesce->right);

        if ($rightType === null) {
            return null;
        }

        // The left must be a NULLABLE of the same type — otherwise the helper
        // would change semantics (mixed coercion) or the `??` is already dead.
        return $this->nullableBuiltinOf($coalesce->left, $coalesce, $ast, $finder) === $rightType
            ? $rightType
            : null;
    }

    /**
     * The type of an "empty default" literal: `[]`/`''`/`0`/`0.0`/`false`, or a
     * `T_Array::EMPTY` / `T_String::EMPTY` / `T_Int::ZERO` / `T_Float::ZERO` /
     * `T_Bool::FALSE` class-constant. Null for anything else (a non-empty
     * default is intentionally not flagged).
     */
    private function emptyLiteralType(Node\Expr $right): ?string
    {
        if ($right instanceof Node\Expr\Array_) {
            return $right->items === [] ? 'array' : null;
        }

        if ($right instanceof Node\Scalar\String_) {
            return $right->value === '' ? 'string' : null;
        }

        if ($right instanceof Node\Scalar\Int_) {
            return $right->value === 0 ? 'int' : null;
        }

        if ($right instanceof Node\Scalar\Float_) {
            return $right->value === 0.0 ? 'float' : null;
        }

        if ($right instanceof Node\Expr\ConstFetch) {
            return strtolower($right->name->toString()) === 'false' ? 'bool' : null;
        }

        if ($right instanceof Node\Expr\ClassConstFetch
            && $right->class instanceof Node\Name
            && $right->name instanceof Node\Identifier
        ) {
            return match ($right->class->getLast() . '::' . $right->name->toString()) {
                'T_Array::EMPTY' => 'array',
                'T_String::EMPTY' => 'string',
                'T_Int::ZERO' => 'int',
                'T_Float::ZERO' => 'float',
                'T_Bool::FALSE' => 'bool',
                default => null,
            };
        }

        return null;
    }

    private function describeRight(Node\Expr $right): string
    {
        return match (true) {
            $right instanceof Node\Expr\Array_ => '[]',
            $right instanceof Node\Scalar\String_ => "''",
            $right instanceof Node\Scalar\Float_ => '0.0',
            $right instanceof Node\Scalar\Int_ => '0',
            $right instanceof Node\Expr\ConstFetch => 'false',
            $right instanceof Node\Expr\ClassConstFetch && $right->name instanceof Node\Identifier => $right->class instanceof Node\Name ? $right->class->getLast() . '::' . $right->name->toString() : '<empty>',
            default => '<empty>',
        };
    }

    /**
     * The builtin type when $expr resolves to a NULLABLE builtin (`?array`,
     * `?string`, `?int`, `?float`, `?bool`, or the `T|null` union form), else
     * null. Resolves a parameter variable, a `$this` property, and an object
     * property (through the codebase index).
     *
     * @param  array<Node>  $ast
     */
    private function nullableBuiltinOf(Node\Expr $expr, Node $context, array $ast, NodeFinder $finder): ?string
    {
        $type = $this->declaredTypeOf($expr, $context, $ast, $finder);

        return $type === null ? null : $this->nullableBuiltinName($type);
    }

    /**
     * @param  array<Node>  $ast
     */
    private function declaredTypeOf(Node\Expr $expr, Node $context, array $ast, NodeFinder $finder): ?Node
    {
        if ($expr instanceof Node\Expr\Variable && is_string($expr->name)) {
            return $this->paramTypeInScope($expr->name, $context, $ast, $finder);
        }

        if (($expr instanceof Node\Expr\PropertyFetch || $expr instanceof Node\Expr\NullsafePropertyFetch)
            && $expr->name instanceof Node\Identifier
        ) {
            // $this->prop — the enclosing class's own property.
            if ($expr->var instanceof Node\Expr\Variable && $expr->var->name === 'this') {
                $class = ReceiverTypeResolver::enclosingClass($context, $ast);

                return $class === null ? null : $this->propertyType($class, $expr->name->toString());
            }

            // $obj->prop — resolve $obj's class through the index, then its property.
            if ($expr->var instanceof Node\Expr\Variable && is_string($expr->var->name)) {
                return $this->objectPropertyType($expr->var->name, $expr->name->toString(), $context, $ast, $finder);
            }
        }

        return null;
    }

    /**
     * @param  array<Node>  $ast
     */
    private function objectPropertyType(string $objVar, string $property, Node $context, array $ast, NodeFinder $finder): ?Node
    {
        $objType = $this->paramTypeInScope($objVar, $context, $ast, $finder);
        $name = $objType instanceof Node\Name ? $objType : ($objType instanceof Node\NullableType && $objType->type instanceof Node\Name ? $objType->type : null);

        if ($name === null || $this->index === null) {
            return null;
        }

        $fqcn = $this->nameFqcn($name);
        $summary = $this->index->classByFqcn(ltrim($fqcn, '\\'));

        if ($summary === null) {
            return null;
        }

        $content = @file_get_contents($summary->filePath);

        if (! is_string($content)) {
            return null;
        }

        $classAst = $this->parse($content);

        if ($classAst === null) {
            return null;
        }

        foreach ((new NodeFinder)->findInstanceOf($classAst, Node\Stmt\Class_::class) as $class) {
            if ($class->name?->toString() === $name->getLast()) {
                return $this->propertyType($class, $property);
            }
        }

        return null;
    }

    private function nullableBuiltinName(Node $type): ?string
    {
        if ($type instanceof Node\NullableType) {
            return $this->builtinName($type->type);
        }

        if ($type instanceof Node\UnionType) {
            $hasNull = false;
            $builtin = null;

            foreach ($type->types as $member) {
                if ($member instanceof Node\Identifier && strtolower($member->toString()) === 'null') {
                    $hasNull = true;

                    continue;
                }

                $name = $member instanceof Node\Identifier ? $this->builtinName($member) : null;

                if ($name === null) {
                    return null; // a non-builtin member — not a clean ?T
                }

                $builtin = $name;
            }

            return $hasNull ? $builtin : null;
        }

        return null;
    }

    private function builtinName(Node $type): ?string
    {
        if (! $type instanceof Node\Identifier) {
            return null;
        }

        $name = strtolower($type->toString());

        return isset(self::WRAPPERS[$name]) ? $name : null;
    }

    private function propertyType(Node\Stmt\Class_ $class, string $property): ?Node
    {
        foreach ($class->getProperties() as $prop) {
            foreach ($prop->props as $declared) {
                if ($declared->name->toString() === $property) {
                    return $prop->type;
                }
            }
        }

        $ctor = $class->getMethod('__construct');

        if ($ctor !== null) {
            foreach ($ctor->params as $param) {
                if ($param->flags !== 0 && $param->var instanceof Node\Expr\Variable && $param->var->name === $property) {
                    return $param->type;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<Node>  $ast
     */
    private function paramTypeInScope(string $name, Node $context, array $ast, NodeFinder $finder): ?Node
    {
        $pos = (int) $context->getStartFilePos();
        $best = null;
        $bestStart = -1;

        $functionLikes = array_merge(
            $finder->findInstanceOf($ast, Node\Stmt\ClassMethod::class),
            $finder->findInstanceOf($ast, Node\Stmt\Function_::class),
            $finder->findInstanceOf($ast, Node\Expr\Closure::class),
            $finder->findInstanceOf($ast, Node\Expr\ArrowFunction::class),
        );

        foreach ($functionLikes as $fn) {
            $start = (int) $fn->getStartFilePos();

            if ($start > $pos || (int) $fn->getEndFilePos() < $pos || $start <= $bestStart) {
                continue;
            }

            foreach ($fn->params as $param) {
                if ($param->var instanceof Node\Expr\Variable && $param->var->name === $name) {
                    $best = $param->type;
                    $bestStart = $start;
                }
            }
        }

        return $best;
    }

    private function nameFqcn(Node\Name $name): string
    {
        $resolved = $name->getAttribute('resolvedName');

        if ($resolved instanceof Node\Name) {
            return '\\' . $resolved->toString();
        }

        return $name->isFullyQualified() ? '\\' . $name->toString() : $name->toString();
    }

}
