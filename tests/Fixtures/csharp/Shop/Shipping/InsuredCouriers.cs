using System.Collections.Generic;
using System.Linq;

namespace Shop.Shipping;

public sealed record InsuredCourier(string Code, bool Overnight, string Depot, int MaxKilos);

public static class InsuredCouriers
{
    public static readonly InsuredCourier[] All = {
        new InsuredCourier("swift", true, "north", 30),
        new InsuredCourier("local", false, "east", 12),
        new InsuredCourier("freight", false, "west", 900),
        new InsuredCourier("cycle", false, "central", 5),
        new InsuredCourier("rail", true, "south", 2000)
    };

    // @sin SideTableTrait
    private static readonly string[] Insured = { "swift", "freight" };

    public static bool CanInsure(InsuredCourier courier) =>
        Insured.Contains(courier.Code)
            && courier.MaxKilos >= 20
            && All.Any(each => each.Depot == courier.Depot && each.Overnight);
}
