namespace Shop.Packing;

// A stocktake's running counts, and the aisles that still need a second pass. The ledger is read by nobody
// here: the counts are written back by the shift that closes the take. Below it, the repair and the look-alike
// that is not the sin, a ledger one method reads.
public sealed class StocktakeSheets
{
    // @sin UnreadCollaborator
    private readonly ITakeLedger ledger;

    private readonly IReadOnlyDictionary<string, int> expected;

    private readonly Dictionary<string, int> counted = new();

    public StocktakeSheets(ITakeLedger ledger, IReadOnlyDictionary<string, int> expected)
    {
        this.ledger = ledger;
        this.expected = expected;
    }

    public void Count(string sku, int counted) => this.counted[sku] = counted;

    public IReadOnlyList<string> NeedsASecondPass()
    {
        return this.expected.Where(held => !this.counted.TryGetValue(held.Key, out var seen) || seen != held.Value)
            .Select(held => held.Key)
            .ToList();
    }
}

// @fixed UnreadCollaborator
public sealed class SettledSheets(IReadOnlyDictionary<string, int> expected)
{
    private readonly Dictionary<string, int> counted = new();

    public void Count(string sku, int counted) => this.counted[sku] = counted;

    public IReadOnlyList<string> NeedsASecondPass()
    {
        return expected.Where(held => !this.counted.TryGetValue(held.Key, out var seen) || seen != held.Value)
            .Select(held => held.Key)
            .ToList();
    }
}

// @righteous UnreadCollaborator
public sealed class SettlingSheets(ITakeLedger ledger)
{
    public void Settle(string sku, int counted) => ledger.Write(sku, counted);
}

public interface ITakeLedger
{
    void Write(string sku, int counted);
}
