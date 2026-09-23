namespace Shop.Pricing;

// A currency's rate is read with `?? throw`, and the failure it throws is a generic one with a sentence
// written right there — the same failure every other rate lookup will describe differently.
public static class Currencies
{
    public static decimal Rate(IReadOnlyDictionary<string, decimal> rates, string currency) =>
        rates.TryGetValue(currency, out var rate)
            ? rate
            // @sin GenericThrow
            : throw new InvalidOperationException($"There is no rate for {currency} today.");
}
