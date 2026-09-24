namespace Shop.Shipping;

public sealed record TimeRange(DateTime From, DateTime Until)
{
    public bool Overlaps(TimeRange other) => From < other.Until && other.From < Until;
}

// @sin CoupledFields
public sealed class DeliverySlot(DateTime from, DateTime until, string driver)
{
    public string Driver => driver;

    public bool Clashes(DeliverySlot other) => new TimeRange(from, until).Overlaps(other.Window());

    public TimeRange Window() => new TimeRange(from, until);
}

// @fixed CoupledFields
public sealed class BookedSlot(TimeRange window, string driver)
{
    public string Driver => driver;

    public bool Clashes(BookedSlot other) => window.Overlaps(other.window);
}
