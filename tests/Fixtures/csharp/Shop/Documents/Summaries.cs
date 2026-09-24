namespace Shop.Documents;

// The line an order is summed up in, on an invoice or a packing slip.
public static class Summaries
{
    // @sin FlagArgument
    public static string Line(string orderId, int cents, bool forCustomer)
    {
        // @sin RedundantElse
        if (forCustomer)
        {
            return $"Order {orderId}: {cents / 100m:C}";
        }
        else
        {
            return $"{orderId};{cents}";
        }
    }

    // @fixed FlagArgument
    public static string CustomerLine(string orderId, int cents) => $"Order {orderId}: {cents / 100m:C}";

    // @fixed FlagArgument
    public static string LedgerLine(string orderId, int cents) => $"{orderId};{cents}";

    // @righteous FlagArgument
    public static string Heading(string? title) => title is null ? "Order" : $"Order — {title}";
}
