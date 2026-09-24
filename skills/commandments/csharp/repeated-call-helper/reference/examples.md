# C# repeated call helper — name what you keep writing — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### csharp-repeated-guard

the same compound condition — `order.Paid && !order.Cancelled` — written at two or more sites, a question with no name

```cs
----------[ Bad ]----------

// in LabelFooters.cs
public static string Footer(Uri link)
{
    if (link.Scheme != Uri.UriSchemeHttp && link.Scheme != Uri.UriSchemeHttps)
    {
        return "";
    }

    return link.ToString();
}

// in Promises.cs
public static bool CanPromiseToday(Dispatchable order) => !order.OnHold && order.Paid && order.Lines > 0;

// in Dispatch.cs
public static string Status(Dispatchable order)
{
    if (order.Paid && !order.OnHold && order.Lines > 0)
    {
        return "pick";
    }

    return "wait";
}

// in TrackingLinks.cs
public static bool IsShowable(Uri link)
{
    return link.Scheme != Uri.UriSchemeHttp && link.Scheme != Uri.UriSchemeHttps ? false : link.Host.Length > 0;
}

----------[ Good ]----------

// in Dispatch.cs
public bool IsReady => Paid && !OnHold && Lines > 0;

// in Dispatch.cs
public static string Label(Dispatchable order) => order.IsReady ? "pick" : "wait";
```

### csharp-repeated-named-call

the same `with` copy — `order with { Status = OrderStatus.Shipped }` — written at two or more sites, an operation the record never named

```cs
----------[ Bad ]----------

// in OrderStages.cs
public static StagedOrder Dispatch(StagedOrder order) => order with { Stage = Stage.Shipped, Note = "on its way" };

// in Backfills.cs
public static IReadOnlyList<StagedOrder> Repaired(IEnumerable<StagedOrder> stuck, DateOnly today, IReadOnlyDictionary<string, DateOnly> packedOn)
{
    var overdue = stuck.Where(order => order.Stage == Stage.Packed && packedOn[order.Id].AddDays(14) < today);

    return overdue.Select(order => order with { Stage = Stage.Shipped, Note = "on its way" }).ToList();
}

// in Handover.cs
public StagedOrder Scanned(StagedOrder order, string courier)
{
    scanLog.Add($"{courier} scanned {order.Id}");

    var shipped = order with { Note = "on its way", Stage = Stage.Shipped };

    if (scanLog.Count > 100)
    {
        scanLog.RemoveAt(0);
    }

    return shipped;
}

----------[ Good ]----------

// in OrderStages.cs
public StagedOrder Shipped() => this with { Stage = Stage.Shipped, Note = "on its way" };

// in OrderStages.cs
public static StagedOrder Send(StagedOrder order) => order.Shipped();
```

### csharp-repeated-type-guard

the same chain of type checks — `node is Invocation call && call.Target is MemberAccess` — written at two or more sites, a shape with no name

```cs
----------[ Bad ]----------

// in Damage.cs
public static int ToInspect(IEnumerable<Package> arrived, int alreadyChecked)
{
    var total = 0;

    foreach (var package in arrived)
    {
        total += package is Crate crate && crate.Lid is ScrewLid ? 1 : 0;
    }

    return total - alreadyChecked;
}

// in Crates.cs
public static string Instructions(Package package)
{
    if (package is Crate crate && crate.Lid is ScrewLid)
    {
        return "use the drill";
    }

    return "lift by hand";
}

// in Palletising.cs
public int SlotsFor(Package package)
{
    var needsRoom = package is Crate crate && crate.Lid is ScrewLid;
    var slots = needsRoom ? 2 : 1;

    return Math.Min(slots, slotsFree);
}

----------[ Good ]----------

// in Crates.cs
public bool IsScrewedCrate => this is Crate crate && crate.Lid is ScrewLid;

// in Crates.cs
public static string Handling(Package package) => package.IsScrewedCrate ? "use the drill" : "lift by hand";
```
