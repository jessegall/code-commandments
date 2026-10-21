namespace Shop.Boards;

// A damage report gathers what each inspector saw, aisle by aisle, and says when it is heavy enough to stop
// the line. The aisles live in a set the report builds as it is walked, and the property at the bottom hands
// that set itself back, so anything holding it can add an aisle nobody inspected.
public sealed class DamageReport(string raisedBy, string shift)
{
    private const int Heavy = 4;

    private readonly HashSet<string> aisles = new();

    private readonly List<string> notes = new();

    public void Saw(string aisle, string note)
    {
        this.aisles.Add(aisle);

        if (note.Length > 0)
        {
            this.notes.Add($"{aisle}: {note}");
        }
    }

    public bool IsHeavy => this.aisles.Count >= Heavy;

    public string Summary()
    {
        var heading = $"{raisedBy} on {shift}";

        return this.notes.Count == 0 ? $"{heading}: nothing written down" : heading + "\n" + string.Join("\n", this.notes.Order());
    }

    // @sin HandedOutCollection
    public HashSet<string> Aisles => this.aisles;
}
