namespace Shop.Orders;

// The refund desk reads the reason from a form and walks it rung by rung against the names RefundReason
// already has, instead of parsing the form into the reason once.
public static class RefundDesk
{
    public static int Priority(string reason)
    {
        // @sin StringMirrorsEnum
        if (reason == "Damaged")
        {
            return 1;
        }
        else if (reason == "Late")
        {
            return 2;
        }

        return 3;
    }
}
