# C# pass the object — demand what you use, not an id and its container — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### csharp-converted-argument

a scalar parameter its callers keep filling with the same conversion — `ReceiptFor(order.Id.ToString())` call after call — because it asks for the converted form instead of the value

```cs
----------[ Bad ]----------

// in Barcodes.cs
public string Top(Parcel parcel) => barcodes.Encode(parcel.Id.ToString());

// in Barcodes.cs
public IEnumerable<string> All(IEnumerable<Parcel> parcels) =>
    parcels.Select(parcel => barcodes.Encode(parcel.Id.ToString()));

// in CreditNotes.cs
public static CreditNote For(IReadOnlyList<ReturnedLine> lines)
{
    var note = new CreditNote();

    foreach (var line in lines)
    {
        note.Credit((decimal) line.Refunded);
    }

    return note;
}

// in CreditNotes.cs
public static CreditNote Single(ReturnedLine line)
{
    var note = new CreditNote();
    note.Credit((decimal) line.Refunded);

    return note;
}

// in CreditSummaries.cs
public decimal Credited(IEnumerable<ReturnedLine> lines, CreditNote note)
{
    foreach (var line in lines.Where(line => line.Refunded > 0))
    {
        note.Credit((decimal) line.Refunded);
    }

    return note.Total;
}

// in BinImports.cs
public int Load(IEnumerable<BinRow> rows)
{
    return rows.Count(row => rack.Claim(int.Parse(row.Aisle), int.Parse(row.Level)));
}

// in BinImports.cs
public bool Reload(BinRow row) => rack.Claim(int.Parse(row.Aisle), int.Parse(row.Level));

----------[ Good ]----------

public string Encode(Guid parcelId) => $"*{parcelId.ToString("N").ToUpperInvariant()}*";
```

### csharp-derived-argument

a call that hands over an object and a projection of it — `Persist(request, request.ChannelId)` — or an object in three pieces, where the method could read them itself

```cs
----------[ Bad ]----------

public void Move(StockMove move) => log.Record(move, move.Sku);

----------[ Good ]----------

public void RecordMove(StockMove move) => lines.Add($"{move.Sku}: {move.Quantity} {move.FromBin}->{move.ToBin}");
```

### csharp-param-resolved-from-param

a method that takes a container and a key and first resolves one against the other — `Rename(Workflow workflow, string nodeId)` doing `workflow.Graph.Node(nodeId)` — when it only wanted what the key names

```cs
----------[ Bad ]----------

public void Reserve(FloorPlan plan, string aisleCode, int slots)
{
    var aisle = plan.Find(aisleCode);
    aisle.FreeSlots -= slots;
}

----------[ Good ]----------

public void ReserveIn(StorageAisle aisle, int slots) => aisle.FreeSlots -= slots;
```
