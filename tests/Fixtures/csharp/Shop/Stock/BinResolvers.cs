namespace Shop.Stock;

public sealed record StorageBin(string Code, int Capacity);

public interface IBinResolver
{
    // @sin DeNulledFinder
    StorageBin? Resolve(string code);
}

public sealed class Putaway(IBinResolver bins)
{
    public int Room(string code, int load)
    {
        var bin = bins.Resolve(code);

        if (bin is null)
        {
            throw new KeyNotFoundException(code);
        }

        return bin.Capacity - load;
    }

    public bool Fits(string code, int load)
    {
        var bin = bins.Resolve(code);

        if (bin == null)
        {
            throw new KeyNotFoundException(code);
        }

        return bin.Capacity >= load;
    }
}
