namespace Shop.Feeds;

// Reads the supplier's price feed. The method below takes the answer out of the task by blocking for it, so
// the thread it stops is the one the read needed back: it works on a quiet morning and deadlocks under load,
// which is the worst way to find a bug. Below it, the repair: await it, and say async in the signature.
public sealed class PriceFeedReader(IPriceFeed feed)
{
    public string Read(string supplier)
    {
        // @sin BlockedAwait
        return feed.ReadAsync(supplier).Result;
    }

    // @fixed BlockedAwait
    public async Task<string> ReadAsync(string supplier)
    {
        return await feed.ReadAsync(supplier);
    }
}

public interface IPriceFeed
{
    Task<string> ReadAsync(string supplier);
}
