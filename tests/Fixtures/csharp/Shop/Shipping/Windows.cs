namespace Shop.Shipping;

// The hours a courier will collect from the warehouse.
public sealed class PickupWindow(int opensAt, int closesAt)
{
    // @sin PositionalTupleReturn
    public (int, int)? Today => opensAt < closesAt ? (opensAt, closesAt) : null;
}
