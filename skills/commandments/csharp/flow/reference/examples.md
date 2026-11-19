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

### csharp-collection-mutated-while-iterated

a `foreach` that adds to or removes from the very collection it walks — the enumerator reads a collection that moved under it, which throws InvalidOperationException

```cs
----------[ Bad ]----------

public void Settle(IReadOnlyCollection<string> packed)
{
    foreach (var line in this.lines)
    {
        if (packed.Contains(line))
        {
            this.lines.Remove(line);
        }
    }
}

----------[ Good ]----------

public void SettlePacked(IReadOnlyCollection<string> packed)
{
    var settled = this.lines.Where(line => packed.Contains(line)).ToList();

    foreach (var line in settled)
    {
        this.lines.Remove(line);
    }
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

### csharp-unnamed-condition

`if (order.IsPaid && customer.Trusted && slot.IsOpen)` — three parts over two objects, so the reader works out what the condition MEANS instead of reading it

```cs
----------[ Bad ]----------

public void Dispatch(Crate crate)
{
    if (crate.Packed && crate.Labelled && bay.IsFree())
    {
        bay.Take(crate);
        sent.Add(crate.Reference);
    }
}

----------[ Good ]----------

// in BayLoading.cs
public bool ReadyFor(LoadingBay bay) => Packed && Labelled && bay.IsFree();

// in BayLoading.cs
public void DispatchTold(Crate crate)
{
    if (crate.ReadyFor(bay))
    {
        bay.Take(crate);
        sent.Add(crate.Reference);
    }
}
```
