namespace Shop.Notifications;

// How a customer asked to be told about their orders.
public enum DeliveryMode
{
    Instant,
    Digest,
    Silent,
    Muted,
}

public sealed class Preferences(DeliveryMode mode)
{
    // @sin EnumCaseOrChain
    public bool IsQuiet => mode is DeliveryMode.Silent or DeliveryMode.Muted or DeliveryMode.Digest;
}
