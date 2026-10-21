namespace Shop.Boards;

// What an aisle's stocktake came to, worked out once from the lines the counter read out and kept in a
// dictionary of its own. Nothing adds to it afterwards: the type answers questions about the count. The method
// at the bottom hands the dictionary itself back, so a caller can write a figure the stocktake never saw.
public sealed class AisleCounts
{
    private const int Short = 0;

    private readonly Dictionary<string, int> counted = new();

    public AisleCounts(IReadOnlyList<(string Line, int Counted)> readOut)
    {
        foreach (var held in readOut)
        {
            this.counted[held.Line] = this.counted.GetValueOrDefault(held.Line) + held.Counted;
        }
    }

    public IReadOnlyList<string> ShortLines()
    {
        return this.counted.Where(held => held.Value == Short).Select(held => held.Key).Order().ToList();
    }

    public int Total() => this.counted.Values.Sum();

    // @sin HandedOutCollection
    public Dictionary<string, int> Counted() => this.counted;
}
