namespace Shop.Notifications;

// The text-message gateway, configured from the shop's settings file.
public sealed class SmsSettings
{
    // @sin BlankStringDefault
    public string ApiKey { get; set; } = string.Empty;

    public string Sender { get; set; } = "SHOP";

    public bool IsConfigured() => !string.IsNullOrWhiteSpace(ApiKey);
}

public sealed class SmsGateway
{
    // @fixed BlankStringDefault
    public string? ApiKey { get; set; }

    public bool IsConfigured() => ApiKey is not null;
}
