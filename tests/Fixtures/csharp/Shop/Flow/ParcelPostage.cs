namespace Shop.Flow;

// ParcelPostage labels parcels by weight and counts what a belt can take.
public sealed class ParcelPostage
{
    public List<string> Labels { get; } = new();

    public int Zone { get; private set; }

    public void Label(int grams)
    {
        // @sin DuplicatedBranchTail
        if (grams > 2000)
        {
            Labels.Add("heavy");
            Zone = Math.Max(Zone, 1);
        }
        else if (grams > 500)
        {
            Labels.Add("medium");
            Zone = Math.Max(Zone, 1);
        }
        else
        {
            Zone = Math.Max(Zone, 1);
        }
    }

    public int Sorted(IEnumerable<int> weights)
    {
        var taken = 0;
        foreach (var grams in weights)
        {
            if (grams > 30000)
            {
                continue;
                // @sin UnreachableStatement
                taken--;
            }
            taken++;
        }

        return taken;
    }

    // @righteous DuplicatedBranchTail
    public void Weigh(int grams)
    {
        if (grams > 2000)
        {
            Labels.Add("heavy");
        }
        Zone = Math.Max(Zone, 1);
    }
}
