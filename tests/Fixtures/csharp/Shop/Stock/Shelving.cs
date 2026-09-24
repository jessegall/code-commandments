namespace Shop.Stock;

// Where on the floor an item is kept.
public enum Aisle
{
    Ambient,
    Chilled,
    Frozen,
}

public sealed class ShelfPlan(Aisle aisle)
{
    // @sin MatchDefaultReturnsNull
    public bool NeedsPower() => aisle switch
    {
        Aisle.Chilled or Aisle.Frozen => true,
        Aisle.Ambient => false,
        _ => false,
    };
}
