namespace Shop.Stock;

public sealed record BinRow(string Aisle, string Level);

public sealed class RackMap
{
    private readonly HashSet<(int Aisle, int Level)> taken = [];

    public bool Claim(int aisle, int level) => taken.Add((aisle, level));
}

public sealed class BinImport(RackMap rack)
{
    public int Load(IEnumerable<BinRow> rows)
    {
        // @sin ConvertedArgument
        return rows.Count(row => rack.Claim(int.Parse(row.Aisle), int.Parse(row.Level)));
    }

    // @sin ConvertedArgument
    public bool Reload(BinRow row) => rack.Claim(int.Parse(row.Aisle), int.Parse(row.Level));

    // @righteous ConvertedArgument
    public bool Corner() => rack.Claim(int.Parse("1"), 1);
}
