namespace Shop.Orders;

// A payment provider's callback, its order status still the string it sent.
public sealed record PaymentCallback(string OrderId, string Status);

// @fixed InArrayMirrorsEnum
public static class OrderStatusRules
{
    public static bool IsSettled(this OrderStatus status) => status switch
    {
        OrderStatus.Paid or OrderStatus.Shipped => true,
        _ => false,
    };
}

public static class PaymentWebhooks
{
    // @sin InArrayMirrorsEnum
    public static bool Settles(PaymentCallback callback) => new[] { "paid", "shipped" }.Contains(callback.Status);

    // @fixed InArrayMirrorsEnum
    public static bool Settled(PaymentCallback callback) => Enum.TryParse(callback.Status, ignoreCase: true, out OrderStatus status) && status.IsSettled();

    // @righteous InArrayMirrorsEnum
    public static bool IsSigned(string algorithm) => new[] { "sha256", "sha512" }.Contains(algorithm);
}
