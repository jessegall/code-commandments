namespace Shop.Shipping;

/// <summary>
/// The parcels that travel chilled. Frozen goods are deliberately not included: they ship with their own courier.
/// </summary>
// @sin NegativeSpaceComment
public sealed class ChilledParcels(IEnumerable<(string Sku, int Celsius)> parcels)
{
    public IEnumerable<string> Skus() => parcels.Where(parcel => parcel.Celsius is > 0 and <= 8).Select(parcel => parcel.Sku);
}

/// <summary>
/// The parcels kept between one and eight degrees; a parcel held at a lower temperature is checked again so a
/// thaw can never pass by accident.
/// </summary>
// @righteous NegativeSpaceComment
public sealed class ThawCheck(IEnumerable<(string Sku, int Celsius)> parcels)
{
    public int Thawed() => parcels.Count(parcel => parcel.Celsius > 0);
}
