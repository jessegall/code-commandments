namespace Shop.Stock;

public static class Zone
{
    public const string Chilled = "cold";
    public const string Bulk = "bulk";
}

public sealed class ZoneMap
{
    private readonly Dictionary<string, List<string>> skusByZone = new();

    public void Place(string zone, string sku)
    {
        if (!skusByZone.TryGetValue(zone, out var skus))
        {
            skus = [];
            skusByZone[zone] = skus;
        }

        skus.Add(sku);
    }
}

public sealed class Receiving(ZoneMap map)
{
    public void Dairy(string sku) => map.Place(Zone.Chilled, sku);

    public void Pallet(string sku)
    {
        // @sin UnnamedVocabularyLiteral
        map.Place("bulk", sku);
    }

    // @righteous UnnamedVocabularyLiteral
    public void Returns(string sku) => map.Place("returns", sku);
}
