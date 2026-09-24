namespace Shop.Pricing;

// The exchange rates the shop converts foreign prices with, refreshed once a day.
public sealed class ExchangeRates(IReadOnlyDictionary<string, decimal> rates)
{
    private DateOnly fetchedOn = DateOnly.MinValue;

    // @sin MemberOutOfOrder
    private const string Base = "EUR";

    public decimal ToBase(string currency, decimal amount) => currency == Base ? amount : amount / rates[currency];

    public bool IsStale(DateOnly today) => fetchedOn < today;
}

// @fixed MemberOutOfOrder
public sealed class TaxRates(IReadOnlyDictionary<string, decimal> rates)
{
    private const string Home = "NL";

    private DateOnly fetchedOn = DateOnly.MinValue;

    public decimal RateFor(string country) => rates.GetValueOrDefault(country, rates[Home]);

    public bool IsStale(DateOnly today) => fetchedOn < today;
}
