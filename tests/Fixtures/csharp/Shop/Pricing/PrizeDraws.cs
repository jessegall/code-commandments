namespace Shop.Pricing;

public sealed class PrizeDraw(IReadOnlyList<string> entrants)
{
    public string Winner(int week)
    {
        // seeded by the week, not random
        // @sin NegativeSpaceComment
        var pick = new Random(week).Next(entrants.Count);

        return entrants[pick];
    }

    public string RunnerUp(int week)
    {
        // the same week always draws the same entrant, so a customer can check the result
        // @fixed NegativeSpaceComment
        var pick = new Random(week * 31).Next(entrants.Count);

        return entrants[pick];
    }
}
