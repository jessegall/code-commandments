namespace Shop.Packing;

// Labels waiting to be printed, oldest first. The clock was wanted while each label carried the time it was
// queued; the label carries its own stamp now, and nothing here reads the clock.
// @sin UnreadCollaborator
public sealed class LabelWaiting(IWaitingClock clock)
{
    private readonly List<string> waiting = new();

    public void Add(string label) => this.waiting.Add(label);

    public string Take()
    {
        var first = this.waiting[0];
        this.waiting.RemoveAt(0);

        return first;
    }

    public int Count() => this.waiting.Count;
}

public interface IWaitingClock
{
    DateTimeOffset Now();
}
