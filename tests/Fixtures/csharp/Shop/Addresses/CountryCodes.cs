namespace Shop.Addresses;

// The country an address ships to, as the carrier's two-letter code.
public static class CountryCodes
{
    public static string Upper(string? country)
    {
        // @sin InlineThrow
        return Normalise(country ?? throw new AddressIncomplete("country"));
    }

    // @fixed InlineThrow
    public static string CheckedUpper(string? country)
    {
        var given = country ?? throw new AddressIncomplete("country");

        return Normalise(given);
    }

    private static string Normalise(string country) => country.Trim().ToUpperInvariant();
}

public sealed class AddressIncomplete(string field) : Exception($"The address has no {field}.");
