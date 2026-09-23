namespace Shop.Notifications;

// Remembers the last address a receipt was mailed to, for the "send again" button.
public sealed class ReceiptMailer
{
    public static string LastAddress = "";

    // @righteous MutableStaticState
    private static readonly string Sender = "receipts@shop.example";

    public string Send(string address)
    {
        // @sin MutableStaticState
        ReceiptMailer.LastAddress = address;

        return $"{Sender} -> {address}";
    }
}
