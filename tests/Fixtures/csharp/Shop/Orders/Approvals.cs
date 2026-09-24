namespace Shop.Orders;

// One sign-off in the chain an order passes before it ships.
public sealed class Approval(string approver, Approval? next)
{
    public string Approver { get; } = approver;

    public Approval? Next { get; } = next;

    // @fixed NonCountingFor
    public IEnumerable<Approval> Chain()
    {
        Approval? current = this;

        while (current != null)
        {
            yield return current;
            current = current.Next;
        }
    }
}

public static class Approvals
{
    public static List<string> Approvers(Approval first)
    {
        var names = new List<string>();

        // @sin NonCountingFor
        for (Approval? step = first; step != null; step = step.Next)
        {
            names.Add(step.Approver);
        }

        return names;
    }

    // @fixed NonCountingFor
    // @sin FeatureEnvy
    public static List<string> Signed(Approval first) => first.Chain().Select(step => step.Approver).ToList();
}
