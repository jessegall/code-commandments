using System.Text.Json;

namespace Shop.Shipping;

public sealed record DispatchTicket(string Reference, DateTime DispatchAt);
public sealed class ManifestUnreadable() : FormatException("The dispatch manifest is unreadable.");

public sealed class DispatchManifest(Dictionary<string, DispatchTicket> tickets)
{
    // @righteous CapabilityProbeHub
    public bool Contains(string reference) => tickets.ContainsKey(reference);

    // @righteous NullableCallback
    public void Publish(string reference, Action<DispatchTicket> notify)
    {
        notify(tickets[reference]);
    }

    // @righteous NullableRegistryLookup
    public DispatchTicket Require(string reference) => tickets[reference];

    // @righteous RawDecodedArrayReturn
    // @righteous MessageAtThrow
    public static DispatchTicket Decode(string payload)
    {
        return JsonSerializer.Deserialize<DispatchTicket>(payload) ?? throw new ManifestUnreadable();
    }
}

public sealed class DispatchSession(DispatchTicket? held)
{
    private DispatchTicket? active = held;

    // @righteous PhantomNullable
    // @righteous MaskedInvariant
    public DateTime? EstimatedDispatch()
    {
        return active?.DispatchAt;
    }

    public void Release() => this.active = null;
}
