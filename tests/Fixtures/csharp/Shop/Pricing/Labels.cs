namespace Shop.Pricing;

// A price label is looked up by SKU, and a SKU with no label gets an empty one — so a shelf edge prints
// blank where it should have said something is missing.
public sealed class Labels(IReadOnlyDictionary<string, string> labels)
{
    // @sin InventedDefault
    public string For(string sku) => labels.TryGetValue(sku, out var label) ? label : "";

    // @righteous InventedDefault
    public string Required(string sku) => labels.TryGetValue(sku, out var label) ? label : throw new KeyNotFoundException(sku);
}
