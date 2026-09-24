namespace Shop.Documents;

using System.Text;

// The email a customer gets when their order ships.
public static class ShippedEmail
{
    public static string Body(string name, string tracking)
    {
        var body = new StringBuilder();

        // @sin AssembledTemplate
        body.AppendLine($"Hi {name},");
        body.AppendLine();
        body.AppendLine("Your order is on its way.");
        body.AppendLine($"Track it with code {tracking}.");

        return body.ToString();
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
