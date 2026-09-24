namespace Shop.Orders;

// An order ready to leave, as the warehouse and the customer desk see it.
public sealed class Dispatchable(bool paid, bool onHold, int lines)
{
    public bool Paid { get; } = paid;

    public bool OnHold { get; } = onHold;

    public int Lines { get; } = lines;

    // @fixed RepeatedGuard
    public bool IsReady => Paid && !OnHold && Lines > 0;
}

public static class Warehouse
{
    public static string Status(Dispatchable order)
    {
        // @sin RepeatedGuard
        if (order.Paid && !order.OnHold && order.Lines > 0)
        {
            return "pick";
        }

        return "wait";
    }

    // @fixed RepeatedGuard
    public static string Label(Dispatchable order) => order.IsReady ? "pick" : "wait";
}
