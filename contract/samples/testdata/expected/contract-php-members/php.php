<?php

// @label named type
// @contract kind/type
// @contract fact/type/form = "interface"
// @contract fact/*/symbol on type = "Named"
interface Named
{
    // @label namedFn function
    // @contract fact/function/form = "method"
    // @contract role/function/returnType -> namedRet
    public function name(): string; // @label namedRet type-expression
}

// @label greets type
// @contract fact/type/form = "trait"
trait Greets
{
}

// @label base type
class Base
{
}

// @label money type
// @contract fact/type/form = "class"
// @contract role/type/bases -> [baseRef]
// @contract role/type/interfaces -> [namedRef]
// @contract role/type/mixins -> [greetsRef]
// @contract role/type/writtenMembers -> [limitConst, size, ctor, nameImpl, limitFn]
// @contract link/type/implicitMembers -> [promoted]
// @label baseRef type-expression 27
// @label namedRef type-expression 43
final class Money extends Base implements Named
{
    // @label greetsRef type-expression
    use Greets;

    // @label limitConst constant
    // @contract kind/constant
    // @contract role/constant/declarators -> [limitDecl]
    // @contract role/constant/declaredType -> limitType
    // @label limitDecl declarator
    // @contract kind/declarator
    // @contract role/declarator/binding -> limitName
    // @contract role/declarator/initializer -> three
    // @label limitType type-expression
    // @label limitName binding
    // @contract kind/binding
    // @contract fact/*/name on binding = "LIMIT"
    // @label three literal
    // @contract fact/literal/literalKind = "integer"
    // @contract fact/*/resolvedType on literal = {"kind":"keyword","name":"int"}
    public const int LIMIT = 3;

    // @label size field
    // @contract kind/field
    // @contract fact/*/effective on field = {"visibility":"private"}
    // @contract role/field/declarators -> [sizeDecl]
    // @contract role/field/declaredType -> sizeType
    // @label sizeDecl declarator
    // @label sizeType type-expression
    // @contract kind/type-expression
    // @contract fact/type-expression/form = "keyword"
    private int $size = 0;

    // @label ctor function
    // @contract fact/function/form = "constructor"
    // @contract role/function/parameters -> [promoted]
    // @contract role/function/body -> ctorBody
    // @label promoted parameter
    // @contract role/parameter/binding -> labelName
    // @contract role/parameter/declaredType -> labelType
    // @contract role/parameter/default -> labelDefault
    // @label ctorBody block
    // @label labelName binding
    // @label labelType type-expression
    // @label labelDefault literal
    public function __construct(public readonly string $label = 'x') {}

    // @label nameImpl function
    // @contract fact/*/obligations on function = [{"symbol":"Named::name","origin":"project"}]
    // @contract fact/*/symbol on function = "Money::name"
    // @contract role/function/returnType -> nameImplRet
    // @contract role/function/body -> nameBody
    public function name(): string // @label nameImplRet type-expression
    // @label nameBody block
    {
        // @contract kind/access
        // @contract fact/access/form = "instance"
        // @contract role/access/subject -> thisRef
        // @contract role/access/member -> memberRef
        // @label thisRef reference 16
        // @label memberRef reference 23
        // @contract fact/reference/form at 16 = "self"
        // @contract fact/*/name on reference at 23 = "label"
        return $this->label;
    }

    // @label limitFn function
    // @contract fact/*/effective on function = {"visibility":"public","static":true}
    public static function limit(): int
    {
        // @contract fact/access/form = "static"
        // @contract fact/*/refers on reference at 16 -> money
        return self::LIMIT;
    }
}
