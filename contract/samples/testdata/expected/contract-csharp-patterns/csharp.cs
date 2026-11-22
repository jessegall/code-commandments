namespace Shop;

// @label radiusParam parameter
// @contract link/type/implicitMembers -> [radiusParam]
public sealed record Circle(int Radius);
public sealed record Square(int Side);

public static class Shapes
{
    // @label shapeRef reference
    // @contract role/dispatch/subject -> shapeRef
    // @contract role/dispatch/arms -> [bigArm, dotArm, shapeArm, positiveArm, otherArm, noneArm]
    public static string Describe(object shape) => shape switch
    {
        // @label bigArm arm
        // @label circleTypeExpr type-expression 9
        // @contract fact/pattern/form at 9 = "record"
        // @contract role/pattern/constrainedType at 9 -> circleTypeExpr
        Circle { Radius: > 10 } => "big",
        // @label dotArm arm
        // @label cBinding binding 16
        // @label dotBody literal 40
        // @contract kind/pattern
        // @contract fact/pattern/form = "type"
        // @contract role/pattern/bindings -> [cBinding]
        // @contract role/arm/body -> dotBody
        Circle c when c.Radius == 0 => "dot",
        // @label shapeArm arm
        Square or Circle => "shape",
        // @label positiveArm arm
        int and > 0 => "positive",
        // @label otherArm arm
        // @label nullPattern pattern 13
        // @contract fact/pattern/form at 9 = "not"
        // @contract role/pattern/subpatterns at 9 -> [nullPattern]
        not null => "other",
        // @label noneArm arm
        _ => "none",
    };

    public static int Size(int[] items) => items switch
    {
        // @contract fact/pattern/form at 9 = "sequence"
        // @contract fact/pattern/form at 21 = "rest"
        [var first, .. var rest] => first + rest.Length,
        [] => 0,
    };

    public static string Kind(int code)
    {
        // @label codeRef reference
        // @contract fact/dispatch/form = "switch"
        // @contract role/dispatch/subject -> codeRef
        switch (code)
        {
            case 1:
                return "one";
            // @label relPattern pattern
            // @label guardCmp comparison
            // @label hundred literal 20
            // @label modExpr binary
            // @label zeroLit literal 41
            // @contract role/arm/patterns -> [relPattern]
            // @contract role/arm/guard -> guardCmp
            // @contract fact/pattern/form = "relational"
            // @contract fact/pattern/operator = "greater"
            // @contract role/pattern/value -> hundred
            // @contract fact/comparison/operator = "equal"
            // @contract fact/comparison/operators = ["equal"]
            // @contract role/comparison/operands -> [modExpr, zeroLit]
            // @contract link/comparison/left -> modExpr
            // @contract link/comparison/right -> zeroLit
            // @contract fact/binary/operator = "modulo"
            case > 100 when code % 2 == 0:
                return "huge";
            // @contract fact/arm/default = true
            default:
                return "other";
        }
    }
}

public static class Resizes
{
    public static Circle Grown(Circle circle) =>
        // @contract kind/construction at 9
        // @contract fact/argument/spread at 9 = "keyed"
        // @contract fact/argument/label at 23 = "Radius"
        circle with { Radius = 2 };
}
