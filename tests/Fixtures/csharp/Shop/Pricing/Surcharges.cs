namespace Shop.Pricing;

// Surcharges are configured as a settings dictionary and read back by a constant key — the constant
// names the field of a record the code never wrote down.
public sealed class Surcharges(IReadOnlyDictionary<string, string> settings)
{
    private const string Weekend = "weekend";

    public decimal Rate()
    {
        var copy = new Dictionary<string, string>(settings);

        // @sin DictionaryBag
        return copy.TryGetValue(Weekend, out var rate) ? decimal.Parse(rate) : 1m;
    }
}
