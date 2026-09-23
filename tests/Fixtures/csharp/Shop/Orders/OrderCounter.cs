namespace Shop.Orders;

// Numbers each order placed at the till.
public sealed class OrderNumbering
{
    private static int placed;

    public int Next()
    {
        // @sin MutableStaticState
        placed++;

        return placed;
    }
}

// @fixed MutableStaticState
public sealed class TillCounter
{
    private int placed;

    public int Next() => ++placed;
}
