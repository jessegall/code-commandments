<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\TypeName;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Property;

/**
 * The type an expression PROVABLY yields, spelled as a declaration would spell it — scalars and
 * nullability included ({@see TypeName::render}). Proven means a literal, a `new X`, or a declaration
 * this codebase can be asked for: a property's type, or a method's declared return. Anything computed
 * is ambiguous by construction and gets no answer, because callers DELETE code on the strength of
 * this, so an unproven type must read as "cannot tell" and never as "no type". The sibling of
 * {@see TypeResolver}, which resolves a receiver CHAIN to a class and knows only class names.
 */
final class ExpressionType
{
    /**
     * The type $expr yields inside $selfFqcn, or null when it cannot be proven.
     */
    public static function of(Codebase $codebase, Node $expr, ?string $selfFqcn): ?string
    {
        return match (true) {
            $expr instanceof String_ => 'string',
            $expr instanceof Int_ => 'int',
            $expr instanceof Float_ => 'float',
            $expr instanceof ConstFetch => self::ofConstant($expr),
            $expr instanceof Array_ => 'array',
            $expr instanceof New_ => self::ofNew($expr),
            $expr instanceof PropertyFetch => self::ofProperty($codebase, $expr, $selfFqcn),
            $expr instanceof MethodCall => self::ofMethodCall($codebase, $expr, $selfFqcn),
            $expr instanceof StaticCall => self::ofStaticCall($codebase, $expr, $selfFqcn),
            default => null,
        };
    }

    /**
     * `true`/`false` are bool; every other constant is a name this cannot resolve.
     */
    private static function ofConstant(ConstFetch $expr): ?string
    {
        return in_array(strtolower($expr->name->toString()), ['true', 'false'], true) ? 'bool' : null;
    }

    /**
     * `new Money(...)` is a `Money` — but `new $class` is whatever the variable held.
     */
    private static function ofNew(New_ $expr): ?string
    {
        return $expr->class instanceof Name ? ltrim($expr->class->toString(), '\\') : null;
    }

    /**
     * `$this->price` is the type the property DECLARES. Only `$this` is followed: another receiver's
     * type is an inference, and this class does not infer.
     */
    private static function ofProperty(Codebase $codebase, PropertyFetch $expr, ?string $selfFqcn): ?string
    {
        $field = self::memberOnThis($expr->var, $expr->name);
        $class = $codebase->declarationMatch($selfFqcn)?->node;

        if ($field === null || ! $class instanceof ClassLike) {
            return null;
        }

        foreach ($class->getProperties() as $property) {
            if (self::declares($property, $field) && $property->type !== null) {
                return TypeName::render($property->type);
            }
        }

        return self::ofPromotedParameter($class, $field);
    }

    /**
     * A promoted constructor parameter is a property too — `public function __construct(private Money $price)`.
     */
    private static function ofPromotedParameter(ClassLike $class, string $field): ?string
    {
        foreach ($class->getMethod('__construct')?->params ?? [] as $param) {
            if ($param->flags !== 0 && $param->var instanceof Variable && $param->var->name === $field && $param->type !== null) {
                return TypeName::render($param->type);
            }
        }

        return null;
    }

    private static function declares(Property $property, string $field): bool
    {
        foreach ($property->props as $declared) {
            if ($declared->name->toString() === $field) {
                return true;
            }
        }

        return false;
    }

    /**
     * `$this->label()` is what `label()` declares it returns. Again only `$this`: a call on anything
     * else needs the receiver inferred, which is the ambiguity this class refuses to guess at.
     */
    private static function ofMethodCall(Codebase $codebase, MethodCall $expr, ?string $selfFqcn): ?string
    {
        $method = self::memberOnThis($expr->var, $expr->name);

        return $method === null ? null : self::declaredReturn($codebase, $selfFqcn, $method, $selfFqcn);
    }

    /**
     * `Money::zero()` is what `zero()` declares — with `self`/`static` read as the class it was
     * called on, which is the only reading a declaration can have.
     */
    private static function ofStaticCall(Codebase $codebase, StaticCall $expr, ?string $selfFqcn): ?string
    {
        if (! $expr->class instanceof Name || ! $expr->name instanceof Node\Identifier) {
            return null;
        }

        $called = ltrim($expr->class->toString(), '\\');
        $called = in_array(strtolower($called), ['self', 'static'], true) ? $selfFqcn : $called;

        return self::declaredReturn($codebase, $called, $expr->name->toString(), $called);
    }

    /**
     * The return type $class::$method declares, rendered — `self`/`static` resolved to $selfFqcn so
     * the answer is a type a declaration could actually spell.
     */
    private static function declaredReturn(Codebase $codebase, ?string $class, string $method, ?string $selfFqcn): ?string
    {
        $declaration = $codebase->declarationMatch($class)?->node;

        if (! $declaration instanceof ClassLike) {
            return null;
        }

        $type = $declaration->getMethod($method)?->returnType;

        if ($type === null) {
            return null;
        }

        $rendered = TypeName::render($type);

        return in_array(strtolower($rendered), ['self', 'static'], true) ? $selfFqcn : $rendered;
    }

    /**
     * The member being reached for, when it is reached for on `$this` by a literal name — the one
     * receiver whose declarations this can read. Null for anything else, which is the ambiguity it
     * refuses to guess at.
     */
    private static function memberOnThis(Node $receiver, Node|string $name): ?string
    {
        return AstNode::isThis($receiver) && $name instanceof Node\Identifier ? $name->toString() : null;
    }
}
