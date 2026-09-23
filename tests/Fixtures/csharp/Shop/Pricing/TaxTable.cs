namespace Shop.Pricing;

// The VAT rates, read once from the tax file.
public static class TaxTable
{
    private static Dictionary<string, int>? rates;

    public static int RateFor(string category)
    {
        // @sin MutableStaticState
        rates ??= new Dictionary<string, int> { ["food"] = 9, ["other"] = 21 };

        return rates[category];
    }
}
