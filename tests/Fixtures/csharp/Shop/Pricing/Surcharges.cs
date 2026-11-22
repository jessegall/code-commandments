namespace Shop.Pricing;

// Surcharges are configured as a settings dictionary and read back by key: the keys name the fields
// of a record the code never wrote down.
public static class Surcharges
{
    // @sin DictionaryBag
    public static decimal Rate(IReadOnlyDictionary<string, string> settings)
    {
        return decimal.Parse(settings["weekend"]) + decimal.Parse(settings["holiday"]);
    }
}
