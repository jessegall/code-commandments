namespace Shop.Shipping;

// Books a parcel with the carrier the customer chose.
public sealed class CarrierPick
{
    private readonly List<string> booked = [];

    public void Book(string parcel, string? carrier)
    {
        // @sin InlineThrow
        booked.Add($"{parcel} via {Label(carrier ?? throw new CarrierNotChosen(parcel))}");
    }

    public int Count() => booked.Count;

    private static string Label(string carrier) => carrier.ToUpperInvariant();
}

public sealed class CarrierNotChosen(string parcel) : Exception($"No carrier was chosen for {parcel}.");
