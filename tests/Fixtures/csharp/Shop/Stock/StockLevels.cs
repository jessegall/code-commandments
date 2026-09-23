namespace Shop.Stock;

// The stock message shown under a product on the shop page.
public static class StockLevels
{
    public static string Message(string product, int units)
    {
        // @sin NestedTernary
        return $"{product}: {(units == 0 ? "sold out" : units < 5 ? "only a few left" : "in stock")}";
    }
}
