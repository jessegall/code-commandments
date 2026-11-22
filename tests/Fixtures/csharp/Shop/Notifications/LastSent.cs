namespace Shop.Notifications;

// Remembers the last address a receipt was mailed to, for the "send again" button.
public sealed class ReceiptMailer
{
    // @righteous MutableStaticState
    private static readonly string Sender = "receipts@shop.example";

    public static string LastAddress = "";

    public string Send(string address)
    {
        // @sin MutableStaticState
        ReceiptMailer.LastAddress = address;

        return $"{Sender} -> {address}";
    }
}
