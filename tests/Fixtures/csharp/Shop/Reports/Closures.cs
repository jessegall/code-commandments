namespace Shop.Reports;

// One line of an order export, its status as the export wrote it.
public sealed record ExportRow(string OrderId, string Status, int Cents);

public static class Closures
{
    // @sin InArrayMirrorsEnum
    public static int Closed(IEnumerable<ExportRow> rows) => rows.Count(row => row.Status is "Delivered" or "Cancelled");
}
