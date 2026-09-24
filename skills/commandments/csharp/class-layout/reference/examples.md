# C# class layout — what the object holds, first — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### csharp-member-after-method

a field, constant or stored property declared below a constructor or a method — the type's state hidden among its behaviour

```cs
----------[ Bad ]----------

private const string Prefix = "INV";

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

private const string Base = "EUR";

----------[ Good ]----------

public sealed class TaxRates(IReadOnlyDictionary<string, decimal> rates)
{
    private const string Home = "NL";

    private DateOnly fetchedOn = DateOnly.MinValue;

    public decimal RateFor(string country) => rates.GetValueOrDefault(country, rates[Home]);

    public bool IsStale(DateOnly today) => fetchedOn < today;
}
```
