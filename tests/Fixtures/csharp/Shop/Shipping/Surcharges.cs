namespace Shop.Shipping;

// The extra a courier charges for a parcel that leaves the mainland.
public sealed class IslandSurcharge(int cents)
{
    /* no longer a flat fee: refactored to scale with the parcel's weight */
    // @sin ArchaeologyComment
    public int For(int grams) => cents * (1 + grams / 1000);
}
