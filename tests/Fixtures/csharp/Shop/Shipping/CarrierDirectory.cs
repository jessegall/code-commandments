namespace Shop.Shipping;

// Looking a carrier up fails with an `Exception` that names nothing — a caller cannot catch "no such
// carrier" without matching on the text, and every lookup re-words the message.
public sealed class CarrierDirectory(IReadOnlyDictionary<string, string> accounts)
{
    public string Account(string carrier)
    {
        if (!accounts.TryGetValue(carrier, out var account))
        {
            // @sin GenericThrow
            throw new Exception($"No carrier is registered as '{carrier}'.");
        }

        return account;
    }

    public string AccountOf(string carrier)
    {
        if (!accounts.TryGetValue(carrier, out var account))
        {
            // @fixed GenericThrow
            throw UnknownCarrier.Named(carrier);
        }

        return account;
    }

    public string Normalised(string carrier)
    {
        if (string.IsNullOrWhiteSpace(carrier))
        {
            // @righteous GenericThrow
            throw new ArgumentException("A carrier needs a name.", nameof(carrier));
        }

        return carrier.Trim().ToLowerInvariant();
    }
}

// @fixed GenericThrow
public sealed class UnknownCarrier : InvalidOperationException
{
    private UnknownCarrier(string message) : base(message) {}

    public static UnknownCarrier Named(string carrier) => new($"No carrier is registered as '{carrier}'.");
}
