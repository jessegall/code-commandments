namespace Shop.Notifications;

// Stops one customer being sent more than a few messages an hour.
public sealed class Throttle
{
    public int SentThisHour { get; private init; }

    // @sin MemberOutOfOrder
    private static readonly TimeSpan Window = TimeSpan.FromHours(1);

    public bool Allows(DateTimeOffset last, DateTimeOffset now) => SentThisHour < 5 || now - last > Window;
}
