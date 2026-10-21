namespace Shop.Packing;

// A till slip priced in two steps, each one writing its answer onto the slip it was handed and handing nothing
// back. The caller reads both figures off a slip whose signatures never said they would be there. Writing a
// property of the slip is feature envy as well, and the fixes differ: these owe their caller a return, while
// Seal owes the slip a Seal of its own.
public sealed class TillPricer
{
    public int Price(IReadOnlyList<int> lines, int taxPercent)
    {
        var slip = new TillSlip();
        this.AddLines(slip, lines);
        this.AddTax(slip, taxPercent);

        return slip.Net + slip.Tax;
    }

    // @sin FilledParameter
    // @sin FeatureEnvy
    private void AddLines(TillSlip slip, IReadOnlyList<int> lines)
    {
        slip.Net = lines.Sum();
    }

    // @sin FilledParameter
    // @sin FeatureEnvy
    private void AddTax(TillSlip slip, int taxPercent)
    {
        slip.Tax = slip.Net * taxPercent / 100;
    }

    // @righteous FilledParameter
    // @sin FeatureEnvy
    public void Seal(TillSlip slip)
    {
        slip.Sealed = true;
    }
}

public sealed class TillSlip
{
    public int Net { get; set; }

    public int Tax { get; set; }

    public bool Sealed { get; set; }
}
