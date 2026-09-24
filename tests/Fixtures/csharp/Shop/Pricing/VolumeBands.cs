namespace Shop.Pricing;

/// <param name="MinimumQuantity">The minimum quantity.</param>
/// <param name="DiscountPercent">The discount percent.</param>
// @sin CeremonyDocblock
public sealed record VolumeBand(int MinimumQuantity, decimal DiscountPercent)
{
    public bool Covers(int quantity) => quantity >= MinimumQuantity;
}

/// <summary>A discount that starts once an order line reaches its quantity.</summary>
/// <param name="From">The first quantity the discount applies to, inclusive.</param>
// @fixed CeremonyDocblock
public sealed record QuantityBreak(int From, decimal Percent)
{
    public decimal Apply(decimal price) => price * (100 - Percent) / 100;
}
