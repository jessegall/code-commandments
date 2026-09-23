# C# duplication — one behaviour, one home — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### duplicate-csharp-method

Copy-pasted code — two+ C# methods, accessors or local functions with an identical body, formatting, comments and attributes aside

```cs
----------[ Bad ]----------

// in Invoice.cs
public IReadOnlyList<string> Lines()
{
    var rows = new List<string>();

    foreach (var line in order.Lines)
    {
        if (line.Quantity <= 0)
        {
            continue;
        }

        rows.Add($"{line.Quantity} x {line.Sku}: {line.Subtotal.Cents / 100m:0.00}");
    }

    return rows;
}

// in PackingSlip.cs
public IReadOnlyList<string> Rows()
{
    var rows = new List<string>();

    foreach (var line in order.Lines)
    {
        if (line.Quantity <= 0)
        {
            continue;
        }

        rows.Add($"{line.Quantity} x {line.Sku}: {line.Subtotal.Cents / 100m:0.00}");
    }

    return rows;
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

// in Replenishment.cs
int Shortfall(string sku)
{
    var have = onHand.TryGetValue(sku, out var count) ? count : 0;
    var missing = minimum - have;

    return missing > 0 ? missing + minimum / 4 : 0;
}

// in Replenishment.cs
int Shortfall(string sku)
{
    var have = onHand.TryGetValue(sku, out var count) ? count : 0;
    var missing = minimum - have;

    return missing > 0 ? missing + minimum / 4 : 0;
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

// in DeliveredMail.cs
public string Compose(Order order)
{
    var mail = new StringBuilder();
    mail.AppendLine($"Hello {customer},");
    mail.AppendLine($"Your order {order.Reference} was delivered.");

    foreach (var item in order.Lines)
    {
        mail.AppendLine($"- {item.Quantity} x {item.Sku}");
    }

    mail.AppendLine("Enjoy your purchase.");

    return mail.ToString();
}

// in ShippedMail.cs
public string Render(Order order)
{
    var body = new StringBuilder();
    body.AppendLine($"Hello {customer},");
    body.AppendLine($"Your order {order.Reference} has shipped.");

    foreach (var line in order.Lines)
    {
        body.AppendLine($"- {line.Quantity} x {line.Sku}");
    }

    body.AppendLine("Thank you for shopping with us.");

    return body.ToString();
}

// in Discounts.cs
public static long Wholesale(IReadOnlyList<OrderLine> lines)
{
    long off = 0;

    for (var index = 0; index < lines.Count; index++)
    {
        var units = lines[index].Quantity;

        if (units >= 100)
        {
            off += lines[index].Subtotal.Cents * 15 / 100;
        }
        else if (units >= 25)
        {
            off += lines[index].Subtotal.Cents * 5 / 100;
        }
    }

    return off;
}

// in Discounts.cs
public static long Retail(IReadOnlyList<OrderLine> lines)
{
    long saved = 0;

    for (var i = 0; i < lines.Count; i++)
    {
        var count = lines[i].Quantity;

        if (count >= 10)
        {
            saved += lines[i].Subtotal.Cents * 10 / 100;
        }
        else if (count >= 3)
        {
            saved += lines[i].Subtotal.Cents * 2 / 100;
        }
    }

    return saved;
}

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
