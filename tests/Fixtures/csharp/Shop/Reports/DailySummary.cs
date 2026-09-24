namespace Shop.Reports;

// The plain-text summary mailed to the shop owner every morning.
public sealed class DailySummary(int orders, int refunds)
{
    // @sin AssembledTemplate
    public string Render(DateOnly day) => string.Join(
        Environment.NewLine,
        $"Summary for {day:yyyy-MM-dd}",
        "--------------------",
        $"Orders:  {orders}",
        $"Refunds: {refunds}");
}
