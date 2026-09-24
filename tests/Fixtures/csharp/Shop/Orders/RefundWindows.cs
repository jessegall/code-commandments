namespace Shop.Orders;

// How long after delivery a customer may still send an order back.
public static class RefundWindows
{
    // formerly lived in the checkout service, and was extracted here
    // @sin ArchaeologyComment
    public static int Days(bool member) => member ? 60 : 30;

    /// <summary>Members get longer to decide, since they return far less often.</summary>
    // @fixed ArchaeologyComment
    public static int DaysFor(bool member) => member ? 60 : 30;
}
