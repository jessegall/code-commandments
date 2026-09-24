namespace Shop.Shipping;

using Shop.Orders;

// The courier scan that marks an order as handed over, and logs who scanned it.
public sealed class Handover(List<string> scanLog)
{
    public StagedOrder Scanned(StagedOrder order, string courier)
    {
        scanLog.Add($"{courier} scanned {order.Id}");

        // @sin RepeatedNamedCall
        var shipped = order with { Note = "on its way", Stage = Stage.Shipped };

        if (scanLog.Count > 100)
        {
            scanLog.RemoveAt(0);
        }

        return shipped;
    }
}
