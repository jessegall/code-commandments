# C# documentation — short, present tense, rare — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### csharp-archaeology-comment

a comment that tells the code's past — `// formerly lived in CheckoutService`, `// refactored to use the cache` — describing a version nobody is reading

```cs
----------[ Bad ]----------

// formerly lived in the checkout service, and was extracted here
public static int Days(bool member) => member ? 60 : 30;

----------[ Good ]----------

/// <summary>Members get longer to decide, since they return far less often.</summary>
public static int DaysFor(bool member) => member ? 60 : 30;
```

### csharp-bloated-docblock

a type whose doc comment runs to two or more paragraphs — usually a sign the type does too much

```cs
----------[ Bad ]----------

/// <summary>
/// Takes a basket through payment.
///
/// It also reserves the stock, books the courier, sends the confirmation email and records the sale in the
/// ledger, retrying each step that fails.
/// </summary>
public sealed class Checkout
{
    public int Steps => 5;
}

----------[ Good ]----------

/// <summary>Takes a basket through payment.</summary>
public sealed class PaymentStep
{
    public int Attempts => 3;
}
```

### csharp-ceremony-docblock

a doc comment whose every tag is empty or only repeats the signature — `<param name="order">The order.</param>`, an empty `<returns>`

```cs
----------[ Bad ]----------

/// <summary>
///
/// </summary>
/// <param name="invoiceNumber"></param>
/// <param name="copies"></param>
/// <returns></returns>
public int Queue(string invoiceNumber, int copies)
{
    for (var copy = 0; copy < copies; copy++)
    {
        queued.Add(invoiceNumber);
    }

    return queued.Count;
}

----------[ Good ]----------

/// <summary>Queues a copy for each page the customer asked to see again, and says how many now wait.</summary>
/// <param name="copies">How many copies the customer asked for; zero queues nothing.</param>
public int QueueFor(string invoiceNumber, int copies)
{
    queued.AddRange(Enumerable.Repeat(invoiceNumber, copies));

    return queued.Count;
}
```

### csharp-dangling-doc-reference

a `<see cref>` that resolves to nothing from where it is written — a name the project no longer declares, or one spelled so it does not reach it

```cs
----------[ Bad ]----------

/// <summary>Splits a <see cref="ShoppingCart"/> into the parcels that need wrapping.</summary>
public sealed class WrapStation
{
    public IReadOnlyList<string> Wrappable(IEnumerable<string> skus) => skus.Where(sku => !sku.StartsWith("DIG-")).ToList();
}

----------[ Good ]----------

/// <summary>Splits a <see cref="Basket"/> into the parcels that need wrapping.</summary>
public sealed class WrapCounter
{
    public int Count(IEnumerable<string> skus) => skus.Count(sku => !sku.StartsWith("DIG-"));
}
```

### csharp-negative-space-comment

a comment defending the code against a reading nobody made — `// not magic, just a day`, `// deliberately not sorted` — saying what it is not instead of what it is

```cs
----------[ Bad ]----------

public string Winner(int week)
{
    // seeded by the week, not random
    var pick = new Random(week).Next(entrants.Count);

    return entrants[pick];
}

----------[ Good ]----------

public string RunnerUp(int week)
{
    // the same week always draws the same entrant, so a customer can check the result
    var pick = new Random(week * 31).Next(entrants.Count);

    return entrants[pick];
}
```

### csharp-restated-comment

a comment above a statement whose every word the statement already spells — `// set the total to the order total` over `var total = order.Total;`

```cs
----------[ Bad ]----------

public decimal On(decimal subtotal)
{
    // set the tip from the subtotal and the percent
    var tip = subtotal * percent / 100;

    return Math.Round(tip, 2);
}

----------[ Good ]----------

public string Offered(decimal subtotal)
{
    var suggested = subtotal * percent / 100;

    return suggested.ToString("0.00");
}
```
