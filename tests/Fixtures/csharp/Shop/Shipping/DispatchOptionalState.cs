using System.Text.Json;

namespace Shop.Absence;

public sealed record Route(string Code, IReadOnlyList<string> Stops);

public sealed class DispatchState
{
    // @sin PhantomNullable
    public Route? Active { get; init; }
    private Route? draft;
    private readonly Dictionary<int, Route> byGate = new();
    private int allocated;

    public void Reserve(int gate, Route route)
    {
        this.draft = route;
        this.byGate[gate] = route;
        this.allocated++;
    }

    public int AssignedStopCount()
    {
        // @sin MaskedInvariant
        return this.draft?.Stops.Count ?? 0;
    }

    public string LabelsAtGate(int gate)
    {
        var firstStop = this.Active.Stops[0];
        return $"{gate}: {firstStop} ({this.allocated} routes)";
    }

    // @sin NullableCallback
    public int SendToGate(int gate, Action<int, string>? write = null)
    {
        if (write != null)
        {
            write(gate, this.Active.Code);
        }
        return this.allocated;
    }

    public Route? LatestAt(int gate, int first)
    {
        if (gate < first)
        {
            return null;
        }
        // @sin NullableRegistryLookup
        return this.byGate.GetValueOrDefault(gate);
    }

    public JsonElement ParseDispatch(ReadOnlySpan<byte> utf8)
    {
        // @sin RawDecodedArrayReturn
        return JsonSerializer.Deserialize<JsonElement>(utf8);
    }

    // @fixed RawDecodedArrayReturn
    public Route ReadRoute(ReadOnlySpan<byte> utf8)
    {
        return JsonSerializer.Deserialize<Route>(utf8) ?? throw new FormatException();
    }
}

public interface IDispatchEnvelope { string Encode(int crate, string country); }
public interface IDispatchEligibility { bool CanUnload(IReadOnlyList<string> stops); }
public interface IDispatchPriority { int Rate(int dueInMinutes); }

// @sin CapabilityProbeHub
public sealed class DispatchProbe(object carrier, string destination)
{
    private readonly object transport = carrier;

    public string Pack(int crate)
    {
        if (this.transport is IDispatchEnvelope envelope)
        {
            return envelope.Encode(crate, destination);
        }
        return "";
    }

    public bool AllowDrop(IReadOnlyList<string> stops)
    {
        if (this.transport is IDispatchEligibility eligibility) { return eligibility.CanUnload(stops); }
        return false;
    }

    public int SortPriority(TimeSpan deadline)
    {
        if (this.transport is IDispatchPriority priority) { return priority.Rate((int)deadline.TotalMinutes); }
        return 0;
    }
}
