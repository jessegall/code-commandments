namespace Shop.Orders;

// @sin ConstClassEnum
public static class PaymentState
{
    public const string Pending = "pending";
    public const string Captured = "captured";
    public const string Refunded = "refunded";
}

// @fixed ConstClassEnum
public enum PaymentStatus
{
    Pending,
    Captured,
    Refunded,
}

// @fixed ConstClassEnum
public static class PaymentStatusRules
{
    public static bool IsSettled(this PaymentStatus status) => status switch
    {
        PaymentStatus.Pending => false,
        PaymentStatus.Captured => true,
        PaymentStatus.Refunded => true,
    };
}

public static class PaymentDesk
{
    public static bool CanRefund(string state) => state == PaymentState.Captured;

    public static bool CanRefund(PaymentStatus status) => status == PaymentStatus.Captured;
}
