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
