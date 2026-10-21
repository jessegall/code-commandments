namespace Shop.Feeds;

// Writes the stocktake back to the warehouse. The save is waited on rather than awaited, and the count after
// it reads its answer the same way, so two threads are held for work that was already in flight.
public sealed class StockFeedWriter(IStockFeed feed)
{
    public int Save(string sku, int counted)
    {
        // @sin BlockedAwait
        feed.SaveAsync(sku, counted).Wait();

        // @sin BlockedAwait
        return feed.CountAsync(sku).GetAwaiter().GetResult();
    }
}

public interface IStockFeed
{
    Task SaveAsync(string sku, int counted);

    Task<int> CountAsync(string sku);
}
