namespace Shop.Addresses;

// A label is printed from a street, a city and a postcode — the same three values the quote takes, side
// by side, in another class: an address nobody named.
public sealed class LabelPrinter
{
    // @sin DataClump
    public string Print(string street, string city, string postcode) => $"{street}\n{postcode} {city}";

    // @fixed DataClump
    public string PrintFor(Address address) => $"{address.Street}\n{address.Postcode} {address.City}";
}

// @fixed DataClump
public sealed record Address(string Street, string City, string Postcode);
