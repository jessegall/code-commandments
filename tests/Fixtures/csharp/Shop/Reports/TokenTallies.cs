namespace Shop.Reports;

// A running tally of what a model run cost. A figure the provider did not report adds nothing to the total,
// which is what a zero handed to a sum means: the absence is left out rather than stood in for.
public sealed class TokenTally
{
    private int total;

    // @righteous InventedDefault
    public void Add(int? reported)
    {
        total += reported ?? 0;
    }

    // @righteous InventedDefault
    public int With(int? reported) => total + (reported ?? 0);

    public int Total() => total;
}
