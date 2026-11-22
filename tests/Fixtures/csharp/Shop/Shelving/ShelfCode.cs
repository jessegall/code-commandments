namespace Shop.Shelving;

public sealed class ShelfCode(string aisle, int slot)
{
    public string Aisle { get; } = aisle;

    public int Slot { get; } = slot;

    public static ShelfCode Parse(string written)
    {
        var parts = written.Split('-', 2);

        // @sin ManufacturedFakeFill
        return new ShelfCode(parts[0], parts.Length > 1 ? int.Parse(parts[1]) : 0);
    }
}
