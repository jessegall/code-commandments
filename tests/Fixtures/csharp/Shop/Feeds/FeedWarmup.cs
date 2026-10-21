namespace Shop.Feeds;

// Two look-alikes that are not the sin. A constructor cannot await, so there is no context for the work to
// need back; and a task completed where it stands has nothing left to wait for, so taking its answer blocks
// on nothing at all.
public sealed class FeedWarmup
{
    private readonly string first;

    // @righteous BlockedAwait
    public FeedWarmup(IPriceFeed feed)
    {
        this.first = feed.ReadAsync("first").Result;
    }

    // @righteous BlockedAwait
    public string Known() => Task.FromResult(this.first).Result;
}
