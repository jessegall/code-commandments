# C# duplication — one behaviour, one home — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### duplicate-csharp-method

Copy-pasted code — two+ C# methods, accessors or local functions with an identical body, formatting, comments and attributes aside

```cs
----------[ Bad ]----------

// in Carriers.cs
get
{
    var cents = grams > 20_000 ? 1_500 : grams > 5_000 ? 700 : 0;

    if (fragile)
    {
        cents += cents / 2 + 250;
    }

    return new Money(cents, "EUR");
}

// in Carriers.cs
get
{
    var cents = grams > 20_000 ? 1_500 : grams > 5_000 ? 700 : 0;

    if (fragile)
    {
        cents += cents / 2 + 250;
    }

    return new Money(cents, "EUR");
}

----------[ Good ]----------

// in Layout.cs
public static IReadOnlyList<string> Describe(IEnumerable<OrderLine> lines) =>
    lines.Where(line => line.Quantity > 0)
        .Select(line => $"{line.Quantity} x {line.Sku}: {line.Subtotal.Cents / 100m:0.00}")
        .ToList();

// in Layout.cs
public IReadOnlyList<string> Rows() => LineLayout.Describe(order.Lines);

// in Layout.cs
public IReadOnlyList<string> Items() => LineLayout.Describe(order.Lines);
```

### near-duplicate-csharp-method

A near-copy — two+ C# methods, accessors or local functions with one control-flow skeleton that differ only in their local names or the literals they use (a key, a route, a message)

```cs
----------[ Bad ]----------

// in Ledgers.cs
public IReadOnlyDictionary<string, int> Counts()
{
    var found = new Dictionary<string, int>();

    try
    {
        foreach (var row in File.ReadAllLines(Path.Combine(folder, "counts.csv")))
        {
            var cells = row.Split(';');
            found[cells[0]] = int.Parse(cells[1]);
        }
    }
    catch (FileNotFoundException)
    {
        return new Dictionary<string, int>();
    }

    return found;
}

// in Ledgers.cs
public IReadOnlyDictionary<string, int> Reservations()
{
    var held = new Dictionary<string, int>();

    try
    {
        foreach (var entry in File.ReadAllLines(Path.Combine(folder, "reservations.csv")))
        {
            var parts = entry.Split(';');
            held[parts[0]] = int.Parse(parts[1]);
        }
    }
    catch (FileNotFoundException)
    {
        return new Dictionary<string, int>();
    }

    return held;
}

----------[ Good ]----------

// in Mailer.cs
public string Compose(Order order, string happened, string signOff)
{
    var body = new StringBuilder();
    body.AppendLine($"Hello {customer},");
    body.AppendLine($"Your order {order.Reference} {happened}.");

    foreach (var line in order.Lines)
    {
        body.AppendLine($"- {line.Quantity} x {line.Sku}");
    }

    body.AppendLine(signOff);

    return body.ToString();
}

// in Mailer.cs
public string Shipped(Order order) => Compose(order, "has shipped", "Thank you for shopping with us.");

// in Mailer.cs
public string Delivered(Order order) => Compose(order, "was delivered", "Enjoy your purchase.");
```
