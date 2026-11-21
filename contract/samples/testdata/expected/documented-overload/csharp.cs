namespace Orders;

public static class Prices
{
    /// <summary>The price in cents.</summary>
    public static int Of(int cents) => cents;

    /// <summary>The price of a count of items.</summary>
    public static int Of(int cents, int count) => cents * count;
}
