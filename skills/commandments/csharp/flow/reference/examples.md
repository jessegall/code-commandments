# C# flow — guard at the top, keep the body flat — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### csharp-coalesced-loop-subject

a `foreach` over `items ?? []` (or `Enumerable.Empty<T>()`, or a new empty list) — the check for a missing collection is hidden in the loop header

```cs
----------[ Bad ]----------

public static List<string> Lines(List<string>? skus)
{
    var lines = new List<string>();

    foreach (var sku in skus ?? [])
    {
        lines.Add($"[ ] {sku}");
    }

    return lines;
}

----------[ Good ]----------

public static List<string> GuardedLines(List<string>? skus)
{
    if (skus is null)
    {
        return [];
    }

    var lines = new List<string>();

    foreach (var sku in skus)
    {
        lines.Add($"[ ] {sku}");
    }

    return lines;
}
```

### deep-csharp-nesting

An `if`, loop or `switch` opening a fourth level of choices inside one C# method — an arrow of conditions and loops

```cs
----------[ Bad ]----------

public IReadOnlyList<string> Reorders()
{
    var reorders = new List<string>();

    foreach (var (warehouse, shelves) in warehouses)
    {
        foreach (var shelf in shelves)
        {
            if (shelf.OnHand < shelf.Minimum)
            {
                if (shelf.Supplier is not null)
                {
                    reorders.Add($"{warehouse}: {shelf.Minimum - shelf.OnHand} x {shelf.Sku} from {shelf.Supplier}");
                }
            }
        }
    }

    return reorders;
}

----------[ Good ]----------

// in Restock.cs
public IReadOnlyList<string> ReordersFlat() =>
    warehouses
        .SelectMany(warehouse => warehouse.Value.Select(shelf => (Warehouse: warehouse.Key, Shelf: shelf)))
        .Where(entry => entry.Shelf.OnHand < entry.Shelf.Minimum && entry.Shelf.Supplier is not null)
        .Select(entry => Reorder(entry.Warehouse, entry.Shelf))
        .ToList();

// in Restock.cs
private static string Reorder(string warehouse, Shelf shelf) =>
    $"{warehouse}: {shelf.Minimum - shelf.OnHand} x {shelf.Sku} from {shelf.Supplier}";
```

### csharp-inline-throw

a `?? throw` inside a call's argument or in front of a member call — the check that stops the method is hidden in the middle of the work

```cs
----------[ Bad ]----------

public static string Upper(string? country)
{
    return Normalise(country ?? throw new AddressIncomplete("country"));
}

----------[ Good ]----------

public static string CheckedUpper(string? country)
{
    var given = country ?? throw new AddressIncomplete("country");

    return Normalise(given);
}
```

### csharp-loop-wrapped-in-if

A `for`, `foreach` or `while` whose whole body is one `if` (no `else`) around real work — the iteration pushed a level deep behind a condition

```cs
----------[ Bad ]----------

public long Bill(Order order, List<string> journal)
{
    long billed = 0;

    foreach (var line in order.Lines)
    {
        if (line.Quantity > 0)
        {
            billed += line.Subtotal.Cents;
            journal.Add($"{line.Sku}: {line.Subtotal.Cents}");
        }
    }

    return billed;
}

----------[ Good ]----------

public long BillFlat(Order order, List<string> journal)
{
    long billed = 0;

    foreach (var line in order.Lines)
    {
        if (line.Quantity <= 0)
        {
            continue;
        }

        billed += line.Subtotal.Cents;
        journal.Add($"{line.Sku}: {line.Subtotal.Cents}");
    }

    return billed;
}
```

### csharp-nested-ternary

a `?:` with another `?:` as one of its branches — several decisions packed into one expression

```cs
----------[ Bad ]----------

public static string Of(int grams) => grams < 500 ? "small" : grams < 5000 ? "medium" : "large";

----------[ Good ]----------

public static string Classed(int grams) => grams switch
{
    < 500 => "small",
    < 5000 => "medium",
    _ => "large",
};
```

### csharp-non-counting-for

a `for` loop whose step assigns the next item instead of moving a counter — a walk written as a count

```cs
----------[ Bad ]----------

public static List<string> Approvers(Approval first)
{
    var names = new List<string>();

    for (Approval? step = first; step != null; step = step.Next)
    {
        names.Add(step.Approver);
    }

    return names;
}

----------[ Good ]----------

// in Approvals.cs
public IEnumerable<Approval> Chain()
{
    Approval? current = this;

    while (current != null)
    {
        yield return current;
        current = current.Next;
    }
}

// in Approvals.cs
public static List<string> Signed(Approval first) => first.Chain().Select(step => step.Approver).ToList();
```

### redundant-csharp-else

An `else` after an `if` branch that already left — it ends in `return`, `throw`, `continue` or `break` — indenting the rest of the method for nothing

```cs
----------[ Bad ]----------

public Money Charge(Order order, string currency)
{
    if (order.Lines.Count == 0)
    {
        throw EmptyOrder.For(order);
    }
    else
    {
        var total = order.Total(currency);

        return total with { Cents = total.Cents + 250 };
    }
}

----------[ Good ]----------

public Money ChargeFlat(Order order, string currency)
{
    if (order.Lines.Count == 0)
    {
        throw EmptyOrder.For(order);
    }

    var total = order.Total(currency);

    return total with { Cents = total.Cents + 250 };
}
```

### csharp-subject-ladder

An `if`/`else if` chain of four or more rungs that each compare the same subject with a constant — a dispatch written as a ladder.

```cs
----------[ Bad ]----------

public static string Colour(OrderStatus status)
{
    if (status == OrderStatus.Placed)
    {
        return "grey";
    }
    else if (status == OrderStatus.Paid)
    {
        return "blue";
    }
    else if (status == OrderStatus.Shipped)
    {
        return "orange";
    }
    else if (status == OrderStatus.Delivered)
    {
        return "green";
    }

    return "red";
}

----------[ Good ]----------

public static string ColourOf(OrderStatus status) => status switch
{
    OrderStatus.Placed => "grey",
    OrderStatus.Paid => "blue",
    OrderStatus.Shipped => "orange",
    OrderStatus.Delivered => "green",
    OrderStatus.Cancelled => "red",
    _ => throw new ArgumentOutOfRangeException(nameof(status)),
};
```
