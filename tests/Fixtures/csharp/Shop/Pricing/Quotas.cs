namespace Shop.Pricing;

// How many discounted units a customer may still buy.
public sealed record Quota(string CustomerId, int Allowed)
{
    private int used;

    private int? remaining;

    // @righteous MutableValueObject
    public int Remaining => remaining ??= Allowed - used;

    public void Spend(int units)
    {
        // @sin MutableValueObject
        this.used += units;
    }
}
