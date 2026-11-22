namespace Shop.Shelving;

public static class ShelfAudit
{
    // @righteous RepeatedStaticFactory
    public static string Either(string written)
    {
        if (written.EndsWith('0'))
        {
            return ShelfCode.Parse(written).Slot.ToString();
        }

        return ShelfCode.Parse(written).Aisle;
    }

    // @righteous RepeatedStaticFactory
    public static bool Moved(string written, string target)
    {
        var code = written;
        var before = ShelfCode.Parse(code).Aisle;
        code = target;

        return before != ShelfCode.Parse(code).Aisle;
    }

    // @righteous RepeatedStaticFactory
    public static bool Apart(string from, string to) => ShelfCode.Parse(from).Aisle == ShelfCode.Parse(to).Aisle;

    // @righteous RepeatedStaticFactory
    public static string Described(string written, Func<string> read)
    {
        try
        {
            return read();
        }
        catch (FormatException error)
        {
            return $"{ShelfCode.Parse(written).Aisle}: {error.Message}";
        }
        catch (InvalidOperationException error)
        {
            return $"{ShelfCode.Parse(written).Aisle} failed: {error.Message}";
        }
    }

    // @righteous RepeatedStaticFactory
    public static string Paired(string first, string second)
    {
        var aisles = new List<string>();
        {
            var written = first;
            aisles.Add(ShelfCode.Parse(written).Aisle);
        }
        if (second != first)
        {
            var written = second;
            aisles.Add(ShelfCode.Parse(written).Aisle);
        }

        return string.Join(",", aisles);
    }
}
