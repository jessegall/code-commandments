namespace Shop.Stock;

public sealed class StorageAisle
{
    public string Code { get; init; } = "";

    public int FreeSlots { get; set; }

    public void Reserve(int slots) => FreeSlots -= slots;
}

public sealed class FloorPlan
{
    public Dictionary<string, StorageAisle> Aisles { get; } = new();

    public StorageAisle Find(string code) => Aisles[code];
}

public sealed class Slotting
{
    // @sin ParamResolvedFromParam
    public void Reserve(FloorPlan plan, string aisleCode, int slots)
    {
        var aisle = plan.Find(aisleCode);
        aisle.FreeSlots -= slots;
    }

    // @righteous ParamResolvedFromParam
    public StorageAisle Resolve(FloorPlan plan, string aisleCode)
    {
        var aisle = plan.Find(aisleCode);

        if (aisle.Code == "")
        {
            throw new KeyNotFoundException(aisleCode);
        }

        return aisle;
    }

    // @fixed ParamResolvedFromParam
    public void ReserveIn(StorageAisle aisle, int slots) => aisle.Reserve(slots);
}
