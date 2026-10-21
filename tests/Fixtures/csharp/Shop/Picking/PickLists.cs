namespace Shop.Picking;

// Picked lines taken off the list the foreach is walking: Remove moves the enumerator's own list, and the next
// read throws InvalidOperationException. Below it, the FIX: decide while walking, change once the walk is over.
public sealed class PickList
{
    private readonly List<string> lines = new();

    public void Settle(IReadOnlyCollection<string> packed)
    {
        foreach (var line in this.lines)
        {
            if (packed.Contains(line))
            {
                // @sin CollectionMutatedWhileIterated
                this.lines.Remove(line);
            }
        }
    }

    // @fixed CollectionMutatedWhileIterated
    public void SettlePacked(IReadOnlyCollection<string> packed)
    {
        var settled = this.lines.Where(line => packed.Contains(line)).ToList();

        foreach (var line in settled)
        {
            this.lines.Remove(line);
        }
    }

    // @righteous CollectionMutatedWhileIterated
    public bool SettleFirst(IReadOnlyCollection<string> packed)
    {
        foreach (var line in this.lines)
        {
            if (packed.Contains(line))
            {
                this.lines.Remove(line);
                return true;
            }
        }

        return false;
    }
}
