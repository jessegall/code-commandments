namespace Shop.Documents;

public sealed record Parcel(Guid Id, string Recipient);

public sealed class BarcodeSheet
{
    public string Encode(string parcelId) => $"*{parcelId.Replace("-", "").ToUpperInvariant()}*";
}

public sealed class DispatchSheet(BarcodeSheet barcodes)
{
    // @sin ConvertedArgument
    public string Top(Parcel parcel) => barcodes.Encode(parcel.Id.ToString());

    public IEnumerable<string> All(IEnumerable<Parcel> parcels) =>
        // @sin ConvertedArgument
        parcels.Select(parcel => barcodes.Encode(parcel.Id.ToString()));
}

public sealed class ParcelBarcodes
{
    // @fixed ConvertedArgument
    public string Encode(Guid parcelId) => $"*{parcelId.ToString("N").ToUpperInvariant()}*";

    public string Top(Parcel parcel) => Encode(parcel.Id);
}
