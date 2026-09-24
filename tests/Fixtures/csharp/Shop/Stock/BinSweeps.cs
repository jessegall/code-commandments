namespace Shop.Stock;

public sealed class BinSweep(Dictionary<string, int> countsByBin)
{
    public List<string> Empty()
    {
        var empty = new List<string>();

        // loop over every bin in the counts by bin
        // @sin RestatedComment
        foreach (var bin in countsByBin.Keys)
        {
            if (countsByBin[bin] == 0)
            {
                empty.Add(bin);
            }
        }

        return empty;
    }
}
