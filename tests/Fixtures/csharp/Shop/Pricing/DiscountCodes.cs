namespace Shop.Pricing;

// A discount code may have been used a number of times, or never recorded at all.
public sealed class DiscountCode
{
    public int? Uses { get; init; }

    // @sin CancelledCoalesce
    public bool IsUnused() => (Uses ?? 0) == 0;
}
