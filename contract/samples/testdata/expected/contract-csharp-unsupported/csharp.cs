namespace Shop;

// @label op_return type-expression 17
// @contract role/unsupported/parts at 1 -> [op_return]
public delegate int Op(int a);

public static class Maths
{
    public static int Add(int a)
    {
        // @label sumBinary binary
        // @contract kind/unsupported
        // @contract fact/unsupported/construct = "CheckedExpression"
        // @contract role/unsupported/parts -> [sumBinary]
        return checked(a + 1);
    }
}
