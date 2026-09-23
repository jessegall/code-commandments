namespace Shop.Notifications;

// The channels a customer can be reached on.
// @sin ConstClassEnum
public static class ContactChannel
{
    public const string Email = "email";
    public const string Sms = "sms";
    public const string Push = "push";
}

// The request headers every outgoing notification carries, by name.
// @righteous ConstClassEnum
public static class NotificationHeaders
{
    public const string Tenant = "X-Tenant";
    public const string Trace = "X-Trace";
}

public static class ChannelCosts
{
    public static int CentsFor(string channel) => channel switch
    {
        ContactChannel.Sms => 5,
        ContactChannel.Push => 0,
        _ => 1,
    };

    public static string HeaderLine(string tenant, string trace) => $"{NotificationHeaders.Tenant}: {tenant}; {NotificationHeaders.Trace}: {trace}";
}
