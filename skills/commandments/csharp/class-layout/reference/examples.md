# C# class layout — what the object holds, first — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### csharp-member-after-method

a field, constant or stored property declared below a constructor or a method — the type's state hidden among its behaviour

```cs
----------[ Bad ]----------

// Hands out invoice numbers in a yearly series.
public sealed class InvoiceNumbers(int year)
{
    private int issued;

    public string Next() => $"{year}-{++issued:D5}";

    private const string Prefix = "INV";

    public string Formatted() => $"{Prefix}/{Next()}";
}

----------[ Good ]----------

public sealed class CreditNoteNumbers(int year)
{
    private const string Prefix = "CN";

    private int issued;

    public string Next() => $"{Prefix}/{year}-{++issued:D5}";
}
```

### csharp-member-out-of-order

a `const` or `static readonly` value declared below a field or a stored property — the top of the type read in no particular order

```cs
----------[ Bad ]----------

// The exchange rates the shop converts foreign prices with, refreshed once a day.
public sealed class ExchangeRates(IReadOnlyDictionary<string, decimal> rates)
{
    private DateOnly fetchedOn = DateOnly.MinValue;

    private const string Base = "EUR";

    public decimal ToBase(string currency, decimal amount) => currency == Base ? amount : amount / rates[currency];

    public bool IsStale(DateOnly today) => fetchedOn < today;
}

----------[ Good ]----------

public sealed class TaxRates(IReadOnlyDictionary<string, decimal> rates)
{
    private const string Home = "NL";

    private DateOnly fetchedOn = DateOnly.MinValue;

    public decimal RateFor(string country) => rates.GetValueOrDefault(country, rates[Home]);

    public bool IsStale(DateOnly today) => fetchedOn < today;
}
```
