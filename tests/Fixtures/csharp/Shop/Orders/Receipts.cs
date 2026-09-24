namespace Shop.Orders;

// The lines printed at the foot of a receipt.
public static class Receipts
{
    // @sin ArrayReturnBag
    public static Dictionary<string, object> Footer(string orderId, int totalCents, DateOnly paidOn) => new()
    {
        ["order"] = orderId,
        ["total"] = totalCents,
        ["paid"] = paidOn,
    };
}

// @fixed ArrayReturnBag
public sealed record ReceiptFooter(string OrderId, int TotalCents, DateOnly PaidOn);

public static class TypedReceipts
{
    // @fixed ArrayReturnBag
    public static ReceiptFooter PaidToday(string orderId, int totalCents) => new(orderId, totalCents, DateOnly.FromDateTime(DateTime.Today));
}
