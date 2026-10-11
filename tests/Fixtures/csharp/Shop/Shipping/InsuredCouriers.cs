using System.Collections.Generic;
using System.Linq;

namespace Shop.Shipping;

public sealed record InsuredCourier(string Code, bool Overnight, string Depot);

public static class InsuredCouriers
{
    public static readonly InsuredCourier[] All = {
        new InsuredCourier("swift", true, "north"),
        new InsuredCourier("local", false, "east"),
        new InsuredCourier("freight", false, "west")
    };

    // @sin SideTableTrait
    private static readonly string[] Insured = { "swift", "freight" };

    public static bool CanInsure(InsuredCourier courier) => Insured.Contains(courier.Code);
}
