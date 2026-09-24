namespace Shop.Documents;

// The tracking link printed at the foot of a shipping label.
public static class LabelPrinter
{
    public static string Footer(Uri link)
    {
        // @sin RepeatedGuard
        if (link.Scheme != Uri.UriSchemeHttp && link.Scheme != Uri.UriSchemeHttps)
        {
            return "";
        }

        return link.ToString();
    }
}
