namespace Shop.Reports;

using Shop.Shipping;

// Counts the packages the weekly damage report has to inspect by hand.
public static class DamageReport
{
    public static int ToInspect(IEnumerable<Package> arrived, int alreadyChecked)
    {
        var total = 0;

        foreach (var package in arrived)
        {
            // @sin RepeatedTypeGuard
            total += package is Crate crate && crate.Lid is ScrewLid ? 1 : 0;
        }

        return total - alreadyChecked;
    }
}
