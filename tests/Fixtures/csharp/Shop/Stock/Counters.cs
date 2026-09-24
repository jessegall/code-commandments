namespace Shop.Stock;

// Counts what is on a shelf, by unit or by case.
public sealed class ShelfCounter(int units, int perCase)
{
    // @sin FlagArgument
    public int Count(bool inCases) => inCases ? units / perCase : units;
}
