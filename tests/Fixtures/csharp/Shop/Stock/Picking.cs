namespace Shop.Stock;

// A pick list skips an empty bin with `continue` — then puts the real work in an `else` anyway, one
// level deeper than the loop needs.
public static class Picking
{
    public static IReadOnlyList<string> List(IReadOnlyDictionary<string, int> bins)
    {
        var picks = new List<string>();

        foreach (var (bin, units) in bins)
        {
            // @sin RedundantElse
            if (units == 0)
            {
                continue;
            }
            else
            {
                picks.Add($"{bin}: {units}");
                picks.Sort(StringComparer.Ordinal);
            }
        }

        return picks;
    }
}
