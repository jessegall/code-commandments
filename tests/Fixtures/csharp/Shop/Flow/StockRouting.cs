namespace Shop.Flow;

public sealed record StockRoute(int Aisle, bool Counted, bool Damaged);

public sealed class StockRouting
{
    private readonly HashSet<int> hidden;
    private string header;
    private long reserve;

    public StockRouting(HashSet<int> hidden, string header, long reserve)
    {
        this.hidden = hidden;
        this.header = header;
        this.reserve = reserve;
    }

    private static bool NeedsRecount(bool counted, bool damaged)
    {
        return !counted || damaged;
    }

    public int HoldRoutes(StockRoute[] visits, Action<int> send)
    {
        var held = 0;
        foreach (var visit in visits)
        {
            // @sin ComputedBooleanArgument
            if (!NeedsRecount(visit.Counted, visit.Damaged))
            {
                continue;
            }
            send(visit.Aisle);
            held++;
        }

        return held;
    }

    public bool IsFirstHeld(StockRoute first)
    {
        // @sin ComputedBooleanArgument
        return NeedsRecount(first.Counted, first.Damaged);
    }

    private string Caption(int aisle,
        // @sin UnusedParameter
        string format)
    {
        if (this.hidden.Contains(aisle))
        {
            return "";
        }
        return $"{this.header}: aisle {aisle:D4}";
    }

    // @sin NarratedCommand
    public void Updates()
    {
        foreach (var slot in this.hidden)
        {
            this.reserve += slot;
        }
    }

    // @sin HandRolledWither
    public StockRouting WithReserve(long reserve)
    {
        return new StockRouting(this.hidden, this.header, reserve);
    }

    // @sin KeyedLookupEnvy
    public decimal Rate(decimal[] prices, StockRoute route)
    {
        return prices[route.Aisle];
    }

    // @sin ScratchStateRestore
    public string[] Inspect(string caption, int[] aisles)
    {
        var remembered = this.header;
        this.header = caption;
        var printed = new string[aisles.Length];
        for (var at = 0; at < aisles.Length; at++)
        {
            printed[at] = this.Caption(aisles[at], caption);
        }
        this.header = remembered;
        return printed;
    }
}
