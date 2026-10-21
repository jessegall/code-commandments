namespace Shop.Loading;

// Loading a crate out of a bay. The condition asks the crate two things and the bay a third, and a reader has
// to hold all three in mind to see that it means "ready to load". Below it, the FIX: the crate answers that
// question under its own name, and each object answers for its own part of it.
public sealed class Crate(string reference, bool packed, bool labelled)
{
    public string Reference { get; } = reference;

    public bool Packed { get; } = packed;

    public bool Labelled { get; } = labelled;

    // @fixed UnnamedCondition
    public bool ReadyFor(LoadingBay bay) => Packed && Labelled && bay.IsFree();
}

public sealed class LoadingBay(string number)
{
    private Crate? standing;

    public string Number { get; } = number;

    public void Take(Crate crate) => standing = crate;

    public bool IsFree() => standing is null;
}

public sealed class BayDispatcher(LoadingBay bay)
{
    private readonly List<string> sent = [];

    public void Dispatch(Crate crate)
    {
        // @sin UnnamedCondition
        if (crate.Packed && crate.Labelled && bay.IsFree())
        {
            bay.Take(crate);
            sent.Add(crate.Reference);
        }
    }

    // @fixed UnnamedCondition
    public void DispatchTold(Crate crate)
    {
        if (crate.ReadyFor(bay))
        {
            bay.Take(crate);
            sent.Add(crate.Reference);
        }
    }

    public IReadOnlyList<string> Sent() => sent;
}
