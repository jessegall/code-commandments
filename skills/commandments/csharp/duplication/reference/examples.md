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
