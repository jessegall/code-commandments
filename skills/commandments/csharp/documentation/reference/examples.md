# C# documentation — short, present tense, rare — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### csharp-archaeology-comment

a comment that tells the code's past — `// formerly lived in CheckoutService`, `// refactored to use the cache` — describing a version nobody is reading

```cs
----------[ Bad ]----------

public static int Days(bool member) => member ? 60 : 30;

----------[ Good ]----------

public static int DaysFor(bool member) => member ? 60 : 30;
```

### csharp-bloated-docblock

a type whose doc comment runs to two or more paragraphs — usually a sign the type does too much

```cs
----------[ Bad ]----------

public sealed class Checkout
{
    public int Steps => 5;
}

----------[ Good ]----------

public sealed class PaymentStep
{
    public int Attempts => 3;
}
```

### csharp-ceremony-docblock

a doc comment whose every tag is empty or only repeats the signature — `<param name="order">The order.</param>`, an empty `<returns>`

```cs
----------[ Bad ]----------

public int Queue(string invoiceNumber, int copies)
{
    for (var copy = 0; copy < copies; copy++)
    {
        queued.Add(invoiceNumber);
    }

    return queued.Count;
}

----------[ Good ]----------

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

public sealed class WrapStation
{
    public IReadOnlyList<string> Wrappable(IEnumerable<string> skus) => skus.Where(sku => !sku.StartsWith("DIG-")).ToList();
}

----------[ Good ]----------

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
