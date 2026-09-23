namespace Shop.Addresses;

public sealed class Quotes(decimal perKilometre)
{
    // @sin DataClump
    public decimal Price(string postcode, string city, string street) =>
        street.Length + city.Length > 40 ? perKilometre * 2 : postcode.StartsWith('1') ? perKilometre : perKilometre * 1.5m;
}
