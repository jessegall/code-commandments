# C# type honesty — a type must say what is true — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### csharp-filled-parameter

a method returning nothing that writes a property of an object it was handed, whose caller then reads what it handed over — the signature says the object goes in and nothing says it comes out changed

```cs
----------[ Bad ]----------

private void ApplyPlan(DealerRecord record)
{
    record.Plan = plans.TryGetValue(record.DealerId, out var plan) ? plan : "free";
}

----------[ Good ]----------

public sealed class DealerFactsReader(IReadOnlyDictionary<string, string> plans, IReadOnlyDictionary<string, string> languages)
{
    public string Gather(string dealerId)
    {
        return $"{dealerId}:{this.PlanOf(dealerId)}:{this.LanguageOf(dealerId)}";
    }

    private string PlanOf(string dealerId) => plans.TryGetValue(dealerId, out var plan) ? plan : "free";

    private string LanguageOf(string dealerId) => languages.TryGetValue(dealerId, out var language) ? language : "nl";
}
```

### csharp-placeholder-filled-data

`new Card(title, "")` — a record's required `string` filled with a blank so the record can be built, hiding a missing value no type check can see

```cs
----------[ Bad ]----------

public static Payout Queued(string sellerId, int cents) => new Payout(sellerId, cents, "");

----------[ Good ]----------

// in Payouts.cs
public sealed record PendingPayout(string SellerId, int Cents);

// in Payouts.cs
public static PendingPayout Queue(string sellerId, int cents) => new PendingPayout(sellerId, cents);
```
