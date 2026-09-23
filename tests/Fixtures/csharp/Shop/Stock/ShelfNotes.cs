namespace Shop.Stock;

// A note the stock counter leaves on a shelf, shown on the next count.
public sealed class ShelfNote
{
    // @sin BlankStringDefault
    public ShelfNote(string shelf, string remark = "")
    {
        Summary = remark.Length == 0 ? shelf : $"{shelf}: {remark}";
    }

    public string Summary { get; }
}
