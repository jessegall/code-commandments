using System.Text.Json;

namespace Shop.Absence;

public sealed record Count(int Units, int Reserved, DateOnly Counted);

public sealed class InventoryState(IEnumerable<KeyValuePair<string, Count>> records)
{
    private readonly Dictionary<string, Count> current = new(records);

    // @sin PhantomNullable
    public Count? InStock { get; init; }
    private Count? lastCount;

    public int Available()
    {
        return this.InStock.Units - this.InStock.Reserved;
    }

    public void Recount(int quantity, DateOnly date)
    {
        this.lastCount = new Count(quantity, 0, date);
    }

    public int QuantityLastCounted()
    {
        // @sin MaskedInvariant
        return this.lastCount?.Units ?? 0;
    }

    // @sin NullableCallback
    public void Replay(IEnumerable<int> counts, Action<int>? report = null)
    {
        foreach (var count in counts)
        {
            if (report != null)
            {
                report(count);
            }
        }
    }

    public Count? Snapshot(string barcode)
    {
        // @sin NullableRegistryLookup
        return this.current.GetValueOrDefault(barcode.Trim());
    }

    public object? ReadLedger(Stream stream)
    {
        // @sin RawDecodedArrayReturn
        return JsonSerializer.Deserialize<object>(stream);
    }
}

public interface IInventoryCounter { int Fill(int expected, string reference); }
public interface IInventoryWeights { decimal Weigh(int row, int column); }
public interface IInventoryNotes { string Describe(int slot); }

// @sin CapabilityProbeHub
public sealed class InventoryProbe
{
    private readonly object ledger;
    private readonly string reference;

    public InventoryProbe(object ledger, string reference)
    {
        this.ledger = ledger;
        this.reference = reference;
    }

    public int Compare(int expected)
    {
        if (this.ledger is IInventoryCounter counted) { return counted.Fill(expected, this.reference); }
        return 0;
    }

    public decimal WeightAt((int Row, int Column) coordinate)
    {
        if (this.ledger is IInventoryWeights measured) { return measured.Weigh(coordinate.Row, coordinate.Column); }
        return 0m;
    }

    public string At(int slot)
    {
        if (this.ledger is IInventoryNotes described) { return described.Describe(slot); }
        return "not counted";
    }
}
