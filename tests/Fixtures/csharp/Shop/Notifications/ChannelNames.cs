namespace Shop.Notifications;

public static class NotifyChannel
{
    public const string Email = "email";
    public const string Sms = "sms";
}

public sealed class NotifyRouter
{
    private readonly HashSet<string> muted = [];

    public bool Allows(string channel, string customer) => !muted.Contains($"{channel}:{customer}");
}

public sealed class OrderAlerts(NotifyRouter router)
{
    public bool CanEmail(string customer) => router.Allows(NotifyChannel.Email, customer);

    public IEnumerable<string> Textable(IEnumerable<string> customers) =>
        // @sin UnnamedVocabularyLiteral
        customers.Where(customer => router.Allows("sms", customer));
}
