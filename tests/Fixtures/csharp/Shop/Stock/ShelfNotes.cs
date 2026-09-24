namespace Shop.Stock;

// A note the stock counter leaves on a shelf, shown on the next count.
public sealed class ShelfNote
{
    public string Summary { get; }

    // @sin BlankStringDefault
    public ShelfNote(string shelf, string remark = "")
    {
        Summary = remark.Length == 0 ? shelf : $"{shelf}: {remark}";
    }
}
