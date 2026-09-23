namespace Shop.Orders;

// An order feed arrives with its status as text, and the feed decides what to do by matching that text
// against the names OrderStatus already declares — the enum, spelled out again as strings.
public sealed class StatusFeed(Action<string> notify)
{
    public void Handle(string reference, string status)
    {
        // @sin StringMirrorsEnum
        switch (status)
        {
            case "shipped":
                notify($"{reference} is on its way");
                break;
            case "delivered":
                notify($"{reference} has arrived");
                break;
        }
    }

    public void HandleParsed(string reference, string status)
    {
        // @fixed StringMirrorsEnum
        switch (Enum.Parse<OrderStatus>(status, ignoreCase: true))
        {
            case OrderStatus.Shipped:
                notify($"{reference} is on its way");
                break;
            case OrderStatus.Delivered:
                notify($"{reference} has arrived");
                break;
        }
    }

    // @righteous StringMirrorsEnum
    public string Channel(string source) => source switch
    {
        "email" => "mail",
        "paid" => "billing",
        _ => "web",
    };
}
