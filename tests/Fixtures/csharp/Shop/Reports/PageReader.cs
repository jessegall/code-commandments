namespace Shop.Reports;

// One page of report rows, and where the next page starts.
public sealed record Page(IReadOnlyList<string> Rows, int? NextOffset);

public interface IPageSource
{
    Page First();

    Page After(Page page);
}

public static class PageReader
{
    public static int CountRows(IPageSource source)
    {
        var total = 0;

        // @sin NonCountingFor
        for (var page = source.First(); ; page = source.After(page))
        {
            total += page.Rows.Count;

            if (page.NextOffset is null)
            {
                return total;
            }
        }
    }
}
