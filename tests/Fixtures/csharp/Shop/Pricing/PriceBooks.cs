namespace Shop.Pricing;

public sealed class PriceBook
{
    public string Currency { get; init; } = "EUR";

    public Dictionary<int, decimal> PricesByProduct { get; } = new();
}

public static class Quotes
{
    // @sin ParamResolvedFromParam
    public static string Quote(PriceBook book, int productId, int units)
    {
        var price = book.PricesByProduct[productId];

        return $"{units * price:0.00} {book.Currency}";
    }
}
