namespace Shop.Notifications;

using Shop.Orders;

// What the customer desk can promise about an order that has not left yet.
public static class CustomerDesk
{
    // @sin RepeatedGuard
    public static bool CanPromiseToday(Dispatchable order) => !order.OnHold && order.Paid && order.Lines > 0;

    // @righteous RepeatedGuard
    public static bool NeedsChasing(Dispatchable order) => !order.Paid && order.Lines > 0;
}
