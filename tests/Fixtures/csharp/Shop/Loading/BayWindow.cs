namespace Shop.Loading;

// The hours a bay is worked. Both conditions here are the righteous twins of a concept with no name: one is a
// range check, three parts about ONE subject, which is a single idea already; the other is a guard standing at
// the top of its body, which says what it means by leaving.
public sealed class BayWindow(int opens, int closes, bool staffed)
{
    private int loaded;

    // @righteous UnnamedCondition
    public bool Covers(int at)
    {
        if (at >= opens && at <= closes && staffed)
        {
            return true;
        }

        return false;
    }

    // @righteous UnnamedCondition
    public void Load(Crate crate, LoadingBay bay)
    {
        var free = bay.IsFree();
        if (!crate.Packed || !free || !staffed)
        {
            return;
        }

        bay.Take(crate);
        loaded++;
    }

    public int Loaded() => loaded;
}
