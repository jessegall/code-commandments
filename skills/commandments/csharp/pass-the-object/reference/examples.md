# C# pass the object — demand what you use, not an id and its container — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### csharp-derived-argument

a call that hands over an object and a projection of it — `Persist(request, request.ChannelId)` — or an object in three pieces, where the method could read them itself

```cs
----------[ Bad ]----------

public sealed class Mover(MoveLog log)
{
    public void Move(StockMove move) => log.Record(move, move.Sku);

    public void Undo(StockMove move)
    {
        var back = move with { FromBin = move.ToBin, ToBin = move.FromBin };
        log.Record(back, back.Sku);
    }
}

----------[ Good ]----------

public sealed class MoveLog
{
    private readonly List<string> lines = [];

    public void Record(StockMove move, string sku) => lines.Add($"{sku}: {move.Quantity} {move.FromBin}->{move.ToBin}");

    public void RecordMove(StockMove move) => lines.Add($"{move.Sku}: {move.Quantity} {move.FromBin}->{move.ToBin}");
}
```

### csharp-param-resolved-from-param

a method that takes a container and a key and first resolves one against the other — `Rename(Workflow workflow, string nodeId)` doing `workflow.Graph.Node(nodeId)` — when it only wanted what the key names

```cs
----------[ Bad ]----------

public sealed class Slotting
{
    public void Reserve(FloorPlan plan, string aisleCode, int slots)
    {
        var aisle = plan.Find(aisleCode);
        aisle.FreeSlots -= slots;
    }

    public StorageAisle Resolve(FloorPlan plan, string aisleCode)
    {
        var aisle = plan.Find(aisleCode);

        if (aisle.Code == "")
        {
            throw new KeyNotFoundException(aisleCode);
        }

        return aisle;
    }

    public void ReserveIn(StorageAisle aisle, int slots) => aisle.Reserve(slots);
}

----------[ Good ]----------

public sealed class Slotting
{
    public void Reserve(FloorPlan plan, string aisleCode, int slots)
    {
        var aisle = plan.Find(aisleCode);
        aisle.FreeSlots -= slots;
    }

    public StorageAisle Resolve(FloorPlan plan, string aisleCode)
    {
        var aisle = plan.Find(aisleCode);

        if (aisle.Code == "")
        {
            throw new KeyNotFoundException(aisleCode);
        }

        return aisle;
    }

    public void ReserveIn(StorageAisle aisle, int slots) => aisle.Reserve(slots);
}
```
