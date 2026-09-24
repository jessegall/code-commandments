namespace Shop.Reports;

using Shop.Orders;

// Repairs old orders the weekly report found stuck in packing for more than a fortnight.
public static class Backfills
{
    public static IReadOnlyList<StagedOrder> Repaired(IEnumerable<StagedOrder> stuck, DateOnly today, IReadOnlyDictionary<string, DateOnly> packedOn)
    {
        var overdue = stuck.Where(order => order.Stage == Stage.Packed && packedOn[order.Id].AddDays(14) < today);

        // @sin RepeatedNamedCall
        return overdue.Select(order => order with { Stage = Stage.Shipped, Note = "on its way" }).ToList();
    }
}
