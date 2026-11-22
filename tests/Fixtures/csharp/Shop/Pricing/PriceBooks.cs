namespace Shop.Pricing;

public sealed record PriceTier(decimal Amount);

public sealed class PriceBook
{
    public Dictionary<int, PriceTier> Tiers { get; } = new();
}

public static class Quotes
{
    // @sin ParamResolvedFromParam
    public static string Quote(PriceBook book, int productId, int units)
    {
        var tier = book.Tiers[productId];

        return $"{units * tier.Amount:0.00}";
    }
}
