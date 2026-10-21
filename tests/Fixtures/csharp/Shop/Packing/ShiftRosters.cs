namespace Shop.Packing;

// The roster for one shift, built by walking the people who signed up and stamping each one's slot onto the
// sheet. The walk writes the sheet's own counts as it goes and answers nothing, and the builder reads the
// counts off to decide whether the shift is covered.
public sealed class ShiftRoster
{
    private const int Cover = 3;

    public bool IsCovered(IReadOnlyList<string> signedUp)
    {
        var sheet = new RosterSheet();
        this.StampSlots(sheet, signedUp);

        return sheet.Filled >= Cover;
    }

    // @sin FilledParameter
    // @sin FeatureEnvy
    private void StampSlots(RosterSheet sheet, IReadOnlyList<string> signedUp)
    {
        for (var at = 0; at < signedUp.Count; at++)
        {
            sheet.Slots.Add($"{at}:{signedUp[at]}");
        }

        sheet.Filled = signedUp.Count;
    }
}

public sealed class RosterSheet
{
    public List<string> Slots { get; } = new();

    public int Filled { get; set; }
}
