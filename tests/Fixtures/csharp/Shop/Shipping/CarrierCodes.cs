namespace Shop.Shipping;

// The tracking link is chosen by matching the carrier code against one pattern after another, each
// rung writing the same variable — a lookup the reader has to walk rung by rung.
public static class CarrierCodes
{
    public static string TrackingUrl(string code, string parcel)
    {
        var url = "";

        // @sin SubjectLadder
        if (code is "dhl")
        {
            url = $"https://dhl.example/track/{parcel}";
        }
        else if (code is "postnl")
        {
            url = $"https://postnl.example/t/{parcel}";
        }
        else if (code is "ups")
        {
            url = $"https://ups.example/tracking?id={parcel}";
        }
        else if (code is "dpd")
        {
            url = $"https://dpd.example/p/{parcel}";
        }

        return url;
    }
}
