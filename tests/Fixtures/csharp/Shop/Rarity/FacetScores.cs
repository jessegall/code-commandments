namespace Shop.Rarity;

// How surprising a facet's value is, in nats, where nothing recorded means nothing surprising. Zero is the
// identity of the sum every reader takes, so a value that was never recorded contributes nothing, and no reader
// could tell a measured zero from an unrecorded one even if the method said so.
public sealed class FacetScores(IReadOnlyDictionary<string, double> recorded)
{
    // @righteous InventedDefault
    public double SelfInformation(string facet)
    {
        if (!recorded.TryGetValue(facet, out var nats))
        {
            return 0;
        }

        return nats;
    }

    public double Score(IReadOnlyList<string> facets)
    {
        var total = 0d;

        foreach (var facet in facets)
        {
            total += SelfInformation(facet);
        }

        return total;
    }
}

// The same shape where a reader does more than add it: the weight is written into a sentence, so an unrecorded
// facet reads as a measured zero and whoever reads the sentence cannot tell.
public sealed class FacetLabels(IReadOnlyDictionary<string, double> recorded)
{
    // @sin InventedDefault
    public double Weight(string facet)
    {
        if (!recorded.TryGetValue(facet, out var weight))
        {
            return 0;
        }

        return weight;
    }

    public string Label(string facet) => "weight " + Weight(facet);
}
