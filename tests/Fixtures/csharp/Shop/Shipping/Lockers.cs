namespace Shop.Shipping;

// A parcel locker a customer can collect from.
public sealed class Locker(int freeSlots, bool open)
{
    // @sin BareStatePredicate
    public bool Accepts() => open && freeSlots > 0;

    // @fixed BareStatePredicate
    public bool IsAccepting => open && freeSlots > 0;

    // @righteous BareStatePredicate
    public bool Fits(int parcels) => open && parcels <= freeSlots;
}
