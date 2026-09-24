namespace Shop.Shipping;

// Checks a parcel against the manifest the courier expects.
public sealed class ManifestCheck(IReadOnlyList<string> expected)
{
    // @sin FlagArgument
    public bool Covers(string parcel, bool strict) => !strict ? expected.Contains(parcel) : expected.Count == 1 && expected[0] == parcel;
}
