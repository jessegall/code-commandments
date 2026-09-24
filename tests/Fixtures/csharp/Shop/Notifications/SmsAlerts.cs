namespace Shop.Notifications;

public sealed record Alert(string Phone, string Text, string Fallback);

public sealed class SmsGateway
{
    public bool Push(Alert alert, string text) => alert.Phone.Length > 0 && text.Length <= 160;
}

public sealed class Alerts(SmsGateway gateway)
{
    // @righteous DerivedArgument
    public bool Short(Alert alert) => gateway.Push(alert, alert.Text);

    public bool Long(Alert alert) => gateway.Push(alert, alert.Fallback);
}
