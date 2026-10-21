# C# fix at the source — fix a value where it is made, not where it breaks — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### csharp-blocked-await

`.Result`, `.Wait()` or `.GetAwaiter().GetResult()` on a task — the thread it blocks is the one the work needed back, so it deadlocks under load and nowhere else

```cs
----------[ Bad ]----------

public string Read(string supplier)
{
    return feed.ReadAsync(supplier).Result;
}

----------[ Good ]----------

public async Task<string> ReadAsync(string supplier)
{
    return await feed.ReadAsync(supplier);
}
```

### csharp-constructor-side-effect

a constructor that calls a method on something it was handed and ignores the result — just creating the object changes something outside it

```cs
----------[ Bad ]----------

public NewsletterPreference(string address, MailingList list)
{
    this.address = address;

    list.Join(address);
}

----------[ Good ]----------

public sealed class NewsletterSignup(string address, MailingList list)
{
    public void Confirm() => list.Join(address);
}
```

### csharp-mutable-static-state

a `static` field that methods write to — state no instance owns, changed by whichever code ran last

```cs
----------[ Bad ]----------

// in Shop/Orders/OrderCounter.cs
namespace Shop.Orders;

// Numbers each order placed at the till.
public sealed class OrderNumbering
{
    private static int placed;

    public int Next()
    {
        placed++;

        return placed;
    }
}

----------[ Good ]----------

// in Shop/Orders/TillCounter.cs
namespace Shop.Orders;

// Numbers each order placed at one till: the count is the instance's own, so two tills never share it.
public sealed class TillCounter
{
    private int placed;

    public int Next() => ++placed;
}
```

### csharp-unread-collaborator

a private readonly field, or a primary constructor parameter, that nothing in the type reads — the type asks for something it does not use

```cs
----------[ Bad ]----------

// A stocktake's running counts, and the aisles that still need a second pass. The ledger is read by nobody
// here: the counts are written back by the shift that closes the take. Below it, the repair and the look-alike
// that is not the sin, a ledger one method reads.
public sealed class StocktakeSheets
{
    private readonly ITakeLedger ledger;

    private readonly IReadOnlyDictionary<string, int> expected;

    private readonly Dictionary<string, int> counted = new();

    public StocktakeSheets(ITakeLedger ledger, IReadOnlyDictionary<string, int> expected)
    {
        this.ledger = ledger;
        this.expected = expected;
    }

    public void Count(string sku, int counted) => this.counted[sku] = counted;

    public IReadOnlyList<string> NeedsASecondPass()
    {
        return this.expected.Where(held => !this.counted.TryGetValue(held.Key, out var seen) || seen != held.Value)
            .Select(held => held.Key)
            .ToList();
    }
}

----------[ Good ]----------

public sealed class SettledSheets(IReadOnlyDictionary<string, int> expected)
{
    private readonly Dictionary<string, int> counted = new();

    public void Count(string sku, int counted) => this.counted[sku] = counted;

    public IReadOnlyList<string> NeedsASecondPass()
    {
        return expected.Where(held => !this.counted.TryGetValue(held.Key, out var seen) || seen != held.Value)
            .Select(held => held.Key)
            .ToList();
    }
}
```
