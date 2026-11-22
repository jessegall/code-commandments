namespace Shop.Documents;

// The email a customer gets when their order ships.
public static class ShippedEmail
{
    public static string Body(string name, string tracking)
    {
        // @sin AssembledTemplate
        return string.Join(Environment.NewLine, $"Hi {name},", "", "Your order is on its way.", $"Track it with code {tracking}.");
    }

    // @fixed AssembledTemplate
    public static string Written(string name, string tracking) => $"""
        Hi {name},

        Your order is on its way.
        Track it with code {tracking}.
        """;

    // @righteous AssembledTemplate
    public static string Items(IEnumerable<string> lines) => string.Join("\n", lines.Select(line => $"- {line}"));
}
