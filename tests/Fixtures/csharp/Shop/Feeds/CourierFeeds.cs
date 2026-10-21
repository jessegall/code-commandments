namespace Shop.Feeds;

// Books a courier for a parcel and reads back what it costs, with a retry while the courier's own queue is
// busy. Every answer is taken out of its task by blocking, so a run that books ten parcels holds ten threads
// for work that was already in flight, and the whole booking round slows to the speed of the slowest call.
public sealed class CourierBooker(ICourierFeed feed)
{
    private const int Tries = 3;

    public int Book(string parcel, string depot)
    {
        for (var attempt = 1; attempt <= Tries; attempt++)
        {
            // @sin BlockedAwait
            var booked = feed.BookAsync(parcel, depot).GetAwaiter().GetResult();

            if (booked)
            {
                break;
            }
        }

        // @sin BlockedAwait
        return feed.CostAsync(parcel).Result;
    }
}

public interface ICourierFeed
{
    Task<bool> BookAsync(string parcel, string depot);

    Task<int> CostAsync(string parcel);
}
