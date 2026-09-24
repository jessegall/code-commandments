namespace Shop.Stock;

using Shop.Shipping;

// How many pallet slots a package takes when it is stacked in the warehouse.
public sealed class PalletPlan(int slotsFree)
{
    public int SlotsFor(Package package)
    {
        // @sin RepeatedTypeGuard
        var needsRoom = package is Crate crate && crate.Lid is ScrewLid;
        var slots = needsRoom ? 2 : 1;

        return Math.Min(slots, slotsFree);
    }
}
