namespace Shop.Stock;

public sealed class ShelfAudit
{
    public List<(string Bin, int Counted)> Counts { get; } = [];

    public int Discrepancy(int expected) => expected - Counts.Sum(count => count.Counted);
}

public sealed class AuditPlanner(ShelfAudit audit)
{
    /// <summary>
    /// The bins to count again, once <see cref="ShelfAudit.Reconcile"/> has settled the rest.
    /// </summary>
    // @sin DanglingDocReference
    public IEnumerable<string> Recount() => audit.Counts.Where(count => count.Counted == 0).Select(count => count.Bin);
}
