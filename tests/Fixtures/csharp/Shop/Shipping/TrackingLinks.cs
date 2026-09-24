namespace Shop.Shipping;

// Checks a courier's tracking link before it is shown to a customer.
public static class TrackingLinks
{
    public static bool IsShowable(Uri link)
    {
        // @sin RepeatedGuard
        return link.Scheme != Uri.UriSchemeHttp && link.Scheme != Uri.UriSchemeHttps ? false : link.Host.Length > 0;
    }
}
