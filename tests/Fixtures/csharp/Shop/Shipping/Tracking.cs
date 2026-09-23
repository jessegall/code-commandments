namespace Shop.Shipping;

// A tracking page keeps the last carrier response it saw — and reads it back with `!`, as if a parcel
// that was never scanned could not exist.
public sealed class Tracking
{
    private string? lastScan;

    // @righteous NullForgiven
    public string Carrier { get; init; } = null!;

    public void Scanned(string scan) => lastScan = scan;

    // @sin NullForgiven
    public string Where() => lastScan!.Split(';')[0];
}
