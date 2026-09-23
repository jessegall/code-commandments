namespace Shop.Stock;

// The state a delivered batch of stock is found in.
public enum BatchCondition
{
    Sound,
    Damaged,
    Expired,
}

public sealed record Batch(string Sku, int Units, BatchCondition Condition);

public static class Batches
{
    // @sin EnumCaseOrChain
    public static int WrittenOff(IEnumerable<Batch> batches) => batches.Where(batch => batch.Condition is BatchCondition.Damaged or BatchCondition.Expired).Sum(batch => batch.Units);

    // @righteous EnumCaseOrChain
    public static string Shelf(Batch batch) => batch.Condition switch
    {
        BatchCondition.Damaged or BatchCondition.Expired => "quarantine",
        _ => "floor",
    };
}
