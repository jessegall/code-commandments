namespace Shop.Stock;

public sealed record ToteItem(string Sku, int Grams);

public sealed class Tote
{
    public List<ToteItem> Items { get; } = [];

    // @fixed FeatureEnvy
    public int TotalGrams() => Items.Sum(item => item.Grams);
}

public sealed class ToteWeigher
{
    // @sin FeatureEnvy
    public int Weigh(Tote tote)
    {
        var grams = 0;

        foreach (var item in tote.Items)
        {
            grams += item.Grams;
        }

        return grams;
    }
}

public sealed class ToteLabeller(LabelQueue queue)
{
    // @righteous FeatureEnvy
    public void LabelAll(Tote tote)
    {
        foreach (var item in tote.Items)
        {
            queue.Enqueue(item);
        }
    }
}

public sealed class LabelQueue
{
    private readonly Queue<ToteItem> waiting = new();

    public void Enqueue(ToteItem item) => waiting.Enqueue(item);
}
