namespace Shop.Stock;

// A cycle count of one shelf against what the ledger says it holds.
public sealed class ShelfCount(IReadOnlyList<int> scanned, int expected)
{
    // @sin PositionalTupleReturn
    public async Task<(int, int)> TallyAsync()
    {
        await Task.Yield();

        return (scanned.Sum(), expected);
    }
}
