namespace Shop.Addresses;

// Whether an address form was filled in far enough to quote a delivery.
public static class PostcodeChecks
{
    // @sin CancelledCoalesce
    public static bool HasPostcode(string? postcode) => (postcode ?? "") != "";

    // @fixed CancelledCoalesce
    public static bool IsGiven(string? postcode) => postcode is not null && postcode != "";

    // @righteous CancelledCoalesce
    public static string Shown(string? postcode) => (postcode ?? "unknown") + " (checked)";
}
