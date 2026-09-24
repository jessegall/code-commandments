namespace Shop.Reports;

// A day's figures, as the dashboard reads them.
public sealed class DailySnapshot(int orders, int refunds)
{
    public IDictionary<string, int> Figures()
    {
        var net = orders - refunds;

        // @sin ArrayReturnBag
        return new Dictionary<string, int>
        {
            { "orders", orders },
            { "refunds", refunds },
            { "net", net },
        };
    }
}
