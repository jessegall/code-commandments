namespace Shop.Shipping;

// Dispatch drains a queue, retries each parcel and checks the carrier's answer inside the retry loop —
// the handling of a refusal is the fourth thing the reader has to hold open.
public sealed class Dispatch(Queue<Parcel> queue, Func<Parcel, int, bool> send)
{
    public int Run(int attempts)
    {
        var refused = 0;

        while (queue.Count > 0)
        {
            var parcel = queue.Dequeue();

            if (parcel.Volume > 0)
            {
                for (var attempt = 1; attempt <= attempts; attempt++)
                {
                    // @sin DeepNesting
                    while (!send(parcel, attempt))
                    {
                        refused++;
                        attempt++;
                    }
                }
            }
        }

        return refused;
    }
}
