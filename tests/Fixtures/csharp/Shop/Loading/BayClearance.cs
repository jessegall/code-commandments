namespace Shop.Loading;

// Walks a round of crates and keeps the reason each one is held back. Three objects are asked in the condition,
// each about something else, and what the three together MEAN has no name anywhere: a reader is left to
// assemble the clearance from an or-chain.
public sealed class BayClearance(bool sweeping)
{
    private readonly Dictionary<string, string> reasons = [];

    public void Walk(IEnumerable<Crate> round, LoadingBay bay)
    {
        foreach (var crate in round)
        {
            // @sin UnnamedCondition
            if (!crate.Packed || !bay.IsFree() || sweeping)
            {
                reasons[crate.Reference] = $"bay {bay.Number}";
            }
        }
    }

    public string Summary() => string.Join(", ", reasons.Select(held => $"{held.Key} held at {held.Value}"));
}
