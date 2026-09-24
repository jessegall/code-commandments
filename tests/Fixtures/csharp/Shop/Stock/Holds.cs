namespace Shop.Stock;

// Stock set aside for an order that has not left the warehouse yet.
public static class Holds
{
    public static int Held(string orderState, int units)
    {
        // @sin InArrayMirrorsEnum
        var holding = new HashSet<string> { "placed", "paid" }.Contains(orderState);

        return holding ? units : 0;
    }
}
