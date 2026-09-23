namespace Shop.Pricing;

// A price list is built row by row, and every row that is not a comment is parsed inside one `if` —
// the parse is the loop's real work, pushed a level in.
public static class PriceList
{
    public static Dictionary<string, long> Parse(string[] rows)
    {
        var prices = new Dictionary<string, long>();

        for (var index = 0; index < rows.Length; index++)
        {
            // @sin LoopWrappedInIf
            if (!rows[index].StartsWith('#'))
            {
                var cells = rows[index].Split(',');
                prices[cells[0].Trim()] = long.Parse(cells[1]);
            }
        }

        return prices;
    }

    // @righteous LoopWrappedInIf
    public static int Comments(string[] rows)
    {
        var count = 0;

        foreach (var row in rows)
        {
            if (row.StartsWith('#'))
            {
                count++;
            }
        }

        return count;
    }
}
