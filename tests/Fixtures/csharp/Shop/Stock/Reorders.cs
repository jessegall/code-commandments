namespace Shop.Stock;

// Decides when a product needs ordering again.
public sealed class ReorderPoint
{
    private static string series = "RP";

    // @righteous MemberOutOfOrder
    private const int MinimumLevel = 1;

    private readonly int dailySales;

    private readonly int leadDays;

    // @sin MemberOutOfOrder
    private const int SafetyDays = 3;

    public ReorderPoint(int dailySales, int leadDays)
    {
        this.dailySales = dailySales;
        this.leadDays = leadDays;
    }

    public int Level() => Math.Max(MinimumLevel, dailySales * (leadDays + SafetyDays));

    public string Label() => $"{series}-{Level()}";
}
