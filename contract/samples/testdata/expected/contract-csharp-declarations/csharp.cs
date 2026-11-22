using System;

namespace Shop;

// @label baseType type
// @contract kind/type
// @contract fact/type/form = "class"
// @contract fact/*/symbol on type = "global::Shop.Base"
// @contract fact/*/modifiers on type = ["public"]
public class Base { }

public interface IShape
{
    // @label shapeReturn type-expression
    // @contract kind/type-expression
    // @contract fact/type-expression/form = "keyword"
    // @contract role/function/returnType -> shapeReturn
    double Area();
}

// @label serializable annotation
// @label serializableRef reference
// @contract kind/annotation
// @contract fact/*/name on annotation = "Serializable"
// @contract role/annotation/callee -> serializableRef
// @contract role/type/attributes -> [serializable]
// @contract role/type/bases -> [baseRef]
// @contract role/type/interfaces -> [shapeRef]
// @contract role/type/typeParameters -> [paramT]
[Serializable]
// @label paramT type-parameter
// @label baseRef type-expression 23
// @label shapeRef type-expression 29
// @contract kind/type-parameter
// @contract fact/*/name on type-parameter = "T"
// @contract fact/type-expression/form at 23 = "named"
public class Box<T> : Base, IShape
{
    // @label nonSerialized annotation
    // @contract kind/field
    // @contract role/field/attributes -> [nonSerialized]
    // @contract role/field/declarators -> [cachedDecl]
    // @contract role/field/declaredType -> cachedType
    // @contract fact/*/modifiers on field = ["private"]
    // @contract fact/*/effective on field = {"visibility":"private"}
    [NonSerialized]
    // @label cachedDecl declarator
    // @label cachedType type-expression
    // @label cachedBinding binding
    // @contract kind/declarator
    // @contract kind/binding
    // @contract role/declarator/binding -> cachedBinding
    // @contract fact/*/name on binding = "cached"
    private int cached;

    // @label obsoleteOld annotation
    // @label oldArg argument
    // @contract kind/constant
    // @contract role/constant/attributes -> [obsoleteOld]
    // @contract role/constant/declarators -> [limitDecl]
    // @contract role/constant/declaredType -> limitType
    // @contract role/annotation/arguments -> [oldArg]
    [Obsolete("old")]
    // @label limitDecl declarator
    // @label limitType type-expression
    // @label limitValue literal
    // @contract kind/literal
    // @contract role/declarator/initializer -> limitValue
    // @contract fact/literal/literalKind = "integer"
    // @contract fact/literal/value = "3"
    public const int Limit = 3;

    // @contract link/field/hooks -> [getter, setter]
    public int Count
    {
        // @label getter function
        // @contract fact/function/form = "get"
        get { return cached; }
        // @label setter function
        // @contract fact/function/form = "set"
        set { cached = value; }
    }

    // @label obsoleteUse annotation
    // @contract role/function/attributes -> [obsoleteUse]
    [Obsolete("use Size")]
    public double Area() => 0;

    // @label writtenT type-expression 32
    // @contract kind/default-value
    // @contract role/default-value/writtenType -> writtenT
    public T Make() => default(T);

    // @contract fact/*/resolvedType on default-value = {"kind":"keyword","name":"int"}
    public int Zero() => default;

    // @label tout type-parameter
    // @label paramItem parameter 31
    // @label paramScale parameter 50
    // @label obsoleteParam annotation
    // @label itemBinding binding 44
    // @label itemType type-expression 42
    // @label scaleDefault literal
    // @label returnTOut type-expression 12
    // @label bodyUnary unary
    // @label bodyDefault default-value
    // @contract kind/parameter at 31
    // @contract role/parameter/attributes at 31 -> [obsoleteParam]
    // @contract role/parameter/binding at 31 -> itemBinding
    // @contract role/parameter/declaredType at 31 -> itemType
    // @contract role/parameter/default at 50 -> scaleDefault
    // @contract role/function/typeParameters -> [tout]
    // @contract role/function/parameters -> [paramItem, paramScale]
    // @contract role/function/returnType -> returnTOut
    // @contract role/function/body -> bodyUnary
    // @contract role/unary/operand -> bodyDefault
    public TOut Convert<TOut>([Obsolete] T item, int scale = 1) => default!;
}

// @contract role/type/writtenMembers -> [lowCase, highCase]
public enum Level
{
    // @label lowCase enum-case
    // @label obsoleteLow annotation
    // @label lowValue literal
    // @contract kind/enum-case
    // @contract role/enum-case/attributes -> [obsoleteLow]
    // @contract role/enum-case/value -> lowValue
    [Obsolete] Low = 1,
    // @label highCase enum-case
    High = 2,
}
