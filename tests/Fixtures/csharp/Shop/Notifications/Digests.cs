namespace Shop.Notifications;

// The weekly digest a customer receives, with the headline of their best offer.
public sealed record Digest(string CustomerId, string Headline, int OfferCount);

public sealed class DigestBuilder(string customerId)
{
    public Digest Empty()
    {
        // @sin PlaceholderFilledData
        var digest = new Digest(customerId, string.Empty, 0);

        return digest;
    }
}
