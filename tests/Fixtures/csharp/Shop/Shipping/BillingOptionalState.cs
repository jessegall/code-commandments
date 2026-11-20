using System;
using System.Collections.Generic;
using System.Text.Json;

namespace Shop.Absence;

public sealed record Invoice(string Memo);

public sealed class BillingState
{
    public Invoice? Draft { get; private set; }
    // @sin PhantomNullable
    public Invoice? Active { get; init; }
    private readonly Dictionary<string, Invoice> records = new();

    public void Prepare(Invoice supplied)
    {
        this.Draft = supplied;
    }

    public string DraftValue()
    {
        // @sin MaskedInvariant
        return this.Draft?.Memo ?? "";
    }

    public string ActiveValue()
    {
        return this.Active.Memo;
    }

    // @sin NullableCallback
    public void Announce(Action? notify = null)
    {
        if (notify != null)
        {
            notify();
        }
    }

    public Invoice? Find(string key)
    {
        // @sin NullableRegistryLookup
        return this.records.GetValueOrDefault(key);
    }

    public JsonDocument Read(string written)
    {
        // @sin RawDecodedArrayReturn
        return JsonDocument.Parse(written);
    }

    // @fixed RawDecodedArrayReturn
    public Invoice Parse(string written)
    {
        return JsonSerializer.Deserialize<Invoice>(written) ?? throw new FormatException();
    }
}

public interface IBillingReady { bool Settled(); }
public interface IBillingPriority { int Amount(); }
public interface IBillingNote { string Describe(); }

// @sin CapabilityProbeHub
public sealed class BillingProbe(object source)
{
    private readonly object backend = source;

    public bool Check()
    {
        if (this.backend is IBillingReady checkedValue) { return checkedValue.Settled(); }
        return false;
    }

    public int Rank()
    {
        if (this.backend is IBillingPriority ranked) { return ranked.Amount(); }
        return 0;
    }

    public string Explain()
    {
        if (this.backend is IBillingNote explained) { return explained.Describe(); }
        return "";
    }
}
