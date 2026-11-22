namespace Shop;

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
