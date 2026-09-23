namespace Shop.Documents;

// The heading printed at the top of an invoice or a packing slip, with an optional strapline under it.
public static class Headings
{
    // @sin BlankStringDefault
    public static string Of(string heading, string strapline = "")
    {
        if (strapline == "")
        {
            return heading;
        }

        return $"{heading} — {strapline}";
    }

    // @fixed BlankStringDefault
    public static string Lined(string heading, string? strapline = null)
    {
        if (strapline is null)
        {
            return heading;
        }

        return $"{heading} — {strapline}";
    }

    // @righteous BlankStringDefault
    public static string Joined(string[] parts, string separator = "")
    {
        return string.Join(separator, parts);
    }
}
