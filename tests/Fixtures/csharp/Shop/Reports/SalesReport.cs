namespace Shop.Reports;

// Sales and returns are both reported over a period — a start, an end and a time zone — passed as three
// loose values to each report, so "the period" is a notion only the parameter lists share.
public sealed class SalesReport(IReadOnlyList<(DateTime At, long Cents)> sales)
{
    // @sin DataClump
    public long Total(DateTime from, DateTime until, string zone) =>
        sales.Where(sale => sale.At >= from && sale.At < until && zone.Length > 0).Sum(sale => sale.Cents);
}

public sealed class ReturnsReport(IReadOnlyList<(DateTime At, string Sku)> returns)
{
    // @sin DataClump
    public int Count(string zone, DateTime until, DateTime from) =>
        returns.Count(entry => entry.At >= from && entry.At < until && zone.Length > 0);
}

public interface IPeriodic
{
    long Sum(DateTime from, DateTime until, string zone);
}

public sealed class Refunds : IPeriodic
{
    // @righteous DataClump
    public long Sum(DateTime from, DateTime until, string zone) => from < until && zone.Length > 0 ? 1 : 0;
}

public sealed class Discounts : IPeriodic
{
    // @righteous DataClump
    public long Sum(DateTime from, DateTime until, string zone) => until > from && zone != "UTC" ? 2 : 0;
}
