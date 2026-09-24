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
