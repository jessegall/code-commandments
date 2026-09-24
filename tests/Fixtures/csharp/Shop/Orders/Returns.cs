namespace Shop.Orders;

// Whether an order can still be sent back.
public sealed class ReturnWindow(DateOnly delivered, DateOnly today)
{
    // @sin BareStatePredicate
    public bool Closes => today > delivered.AddDays(30);
}
