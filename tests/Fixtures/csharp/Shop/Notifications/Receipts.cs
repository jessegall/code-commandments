namespace Shop.Notifications;

// A receipt goes to the customer's email — and when there is none, it is "sent" to an empty address the
// mailer cannot tell from a real one.
public sealed class Receipts(Action<string, string> mail)
{
    public void Send(string? email, string body)
    {
        // @sin InventedDefault
        mail(email ?? "", body);
    }

    public void SendIfAddressed(string? email, string body)
    {
        if (email is null)
        {
            return;
        }

        // @fixed InventedDefault
        mail(email, body);
    }

    public void SendIn(string? currency, string body)
    {
        // @righteous InventedDefault
        mail(body, currency ?? "EUR");
    }
}
