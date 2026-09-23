namespace Shop.Shipping;

// The size class printed on a parcel label, by weight.
public static class ParcelSizes
{
    // @sin NestedTernary
    public static string Of(int grams) => grams < 500 ? "small" : grams < 5000 ? "medium" : "large";

    // @fixed NestedTernary
    public static string Classed(int grams) => grams switch
    {
        < 500 => "small",
        < 5000 => "medium",
        _ => "large",
    };
}
