# C# flow — guard at the top, keep the body flat — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

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

### redundant-csharp-else

An `else` after an `if` branch that already left — it ends in `return`, `throw`, `continue` or `break` — indenting the rest of the method for nothing

```cs
----------[ Bad ]----------

public Money Charge(Order order, string currency)
{
    if (order.Lines.Count == 0)
    {
        throw new InvalidOperationException($"Order {order.Reference} has nothing to charge.");
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
        throw new InvalidOperationException($"Order {order.Reference} has nothing to charge.");
    }

    var total = order.Total(currency);

    return total with { Cents = total.Cents + 250 };
}
```
