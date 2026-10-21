namespace Shop.Picking;

// Expired reservations removed from the dictionary whose Keys the foreach is walking: Keys is a view onto the
// dictionary, not a copy of it, so the walk throws on the key after the first removal.
public sealed class ReservationSweep
{
    public void Sweep(Dictionary<string, int> reservations)
    {
        foreach (var code in reservations.Keys)
        {
            if (reservations[code] == 0)
            {
                // @sin CollectionMutatedWhileIterated
                reservations.Remove(code);
            }
        }
    }

    // @fixed CollectionMutatedWhileIterated
    public void SweepExpired(Dictionary<string, int> reservations)
    {
        var expired = reservations.Where(held => held.Value == 0).Select(held => held.Key).ToList();

        foreach (var code in expired)
        {
            reservations.Remove(code);
        }
    }
}
