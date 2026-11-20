using System.Collections.Generic;

namespace Shop.Flow;

public sealed record ReceiptRoute(string Code, bool Printed, bool Verified);

public sealed class ReceiptRouting
{
    private string zone;
    private string depot;
    private int counter;

    public ReceiptRouting(string zone, string depot, int counter)
    {
        this.zone = zone;
        this.depot = depot;
        this.counter = counter;
    }

    private static bool NeedsReview(bool left, bool right)
    {
        return left && !right;
    }

    public bool Check(ReceiptRoute front, ReceiptRoute back)
    {
        // @sin ComputedBooleanArgument
        var first = NeedsReview(front.Printed, front.Verified);
        // @sin ComputedBooleanArgument
        var second = NeedsReview(back.Printed, back.Verified);
        return first || second;
    }

    private string Caption(string value,
        // @sin UnusedParameter
        string prefix)
    {
        return this.zone + value;
    }

    // @sin NarratedCommand
    public void Dispatches()
    {
        this.counter = this.counter + 1;
    }

    // @sin HandRolledWither
    public ReceiptRouting WithCounter(int next)
    {
        return new ReceiptRouting(this.zone, this.depot, next);
    }

    // @sin KeyedLookupEnvy
    public string Owner(Dictionary<string, string> owners, ReceiptRoute route)
    {
        return owners[route.Code];
    }

    // @sin ScratchStateRestore
    public string Inspect(string probe)
    {
        var saved = this.zone;
        this.zone = probe;
        var caption = Caption(probe, this.depot);
        this.zone = saved;
        return caption;
    }

    // @righteous NarratedCommand
    public void ResetCounter()
    {
        this.counter = 0;
    }
}
