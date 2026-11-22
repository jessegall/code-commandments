namespace Shop;

public sealed record Circle(int Radius);
public sealed record Square(int Side);

public static class Shapes
{
    public static string Describe(object shape) => shape switch
    {
        Circle { Radius: > 10 } => "big",
        Circle c when c.Radius == 0 => "dot",
        Square or Circle => "shape",
        int and > 0 => "positive",
        not null => "other",
        _ => "none",
    };

    public static int Size(int[] items) => items switch
    {
        [var first, .. var rest] => first + rest.Length,
        [] => 0,
    };

    public static string Kind(int code)
    {
        switch (code)
        {
            case 1:
                return "one";
            case > 100 when code % 2 == 0:
                return "huge";
            default:
                return "other";
        }
    }
}
