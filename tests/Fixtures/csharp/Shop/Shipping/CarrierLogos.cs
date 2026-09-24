namespace Shop.Shipping;

// The carriers a parcel can leave with.
public enum Courier
{
    Postal,
    Express,
    Freight,
}

public static class CarrierLogos
{
    // @sin MatchDefaultReturnsNull
    public static string? LogoFor(Courier courier) => courier switch
    {
        Courier.Postal => "postal.svg",
        Courier.Express => "express.svg",
        Courier.Freight => "freight.svg",
        _ => null,
    };

    // @fixed MatchDefaultReturnsNull
    public static string Logo(Courier courier) => courier switch
    {
        Courier.Postal => "postal.svg",
        Courier.Express => "express.svg",
        Courier.Freight => "freight.svg",
        _ => throw new ArgumentOutOfRangeException(nameof(courier), courier, null),
    };

    // @righteous MatchDefaultReturnsNull
    public static string? TrackingHost(Courier courier) => courier switch
    {
        Courier.Express => "track.express.example",
        _ => null,
    };
}
