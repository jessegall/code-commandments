using System;

namespace Shop;

public interface IShape
{
    double Area();
}

// @label circleType type
// @contract fact/*/symbol on type = "global::Shop.Circle"
// @contract role/type/writtenMembers -> [circleArea]
public sealed class Circle : IShape
{
    // @label circleArea function
    // @contract fact/*/obligations on function = [{"symbol":"global::Shop.IShape.Area()","origin":"project"}]
    // @contract fact/*/effective on function = {"visibility":"public"}
    public double Area() => 1;
}

public static class Tools
{
    // @label makeFn function
    // @label newT type-expression 62
    // @contract fact/function/form = "method"
    // @contract fact/*/modifiers on function = ["public","static"]
    // @contract role/construction/type -> newT
    public static T Make<T>() where T : IShape, new() => new T();

    // @label fpParam parameter 46
    // @label fpReturn type-expression 51
    // @contract fact/type-expression/form at 36 = "function"
    // @contract role/type-expression/parameters at 36 -> [fpParam]
    // @contract role/type-expression/returnType at 36 -> fpReturn
    public static unsafe int Apply(delegate*<int, int> apply) => apply(1);

    // @label flagSubject reference
    // @contract kind/dispatch
    // @contract fact/dispatch/form = "match"
    // @contract fact/dispatch/exhaustive = true
    // @contract role/dispatch/subject -> flagSubject
    // @contract role/dispatch/arms -> [armTrue, armFalse]
    public static string Of(bool flag) => flag switch
    {
        // @label armTrue arm
        // @label yesBody literal 17
        // @contract kind/arm
        // @contract role/arm/body -> yesBody
        // @contract fact/literal/literalKind at 17 = "string"
        // @contract fact/literal/value at 17 = "yes"
        true => "yes",
        // @label armFalse arm
        false => "no",
    };

    public static void Run(object gate)
    {
        // @contract kind/try
        // @contract role/try/body -> tryBody
        // @contract role/try/finally -> finallyBlock
        try
        // @label tryBody block
        {
            // @label circleArg type-expression 30
            // @contract role/call/typeArguments -> [circleArg]
            // @contract fact/*/target on call -> makeFn
            // @contract fact/*/refers on type-expression at 30 -> circleType
            var shape = Make<Circle>();
        }
        finally
        // @label finallyBlock block
        {
            // @label writeCall call
            // @label writeAccess access
            // @label consoleRef reference 13
            // @label writeLineRef reference 21
            // @label doneArg argument
            // @label doneLiteral literal
            // @contract kind/expression-statement
            // @contract role/expression-statement/expression -> writeCall
            // @contract kind/call
            // @contract role/call/callee -> writeAccess
            // @contract role/call/arguments -> [doneArg]
            // @contract kind/access
            // @contract role/access/subject -> consoleRef
            // @contract role/access/member -> writeLineRef
            // @contract fact/access/form = "static"
            // @contract kind/argument
            // @contract role/argument/value -> doneLiteral
            // @contract fact/argument/passing = "value"
            // @contract kind/reference at 13
            Console.WriteLine("done");
        }

        if (gate is not string) { }
        if (gate is not Circle) { }
    }
}
