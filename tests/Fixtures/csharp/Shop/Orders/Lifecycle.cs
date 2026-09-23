namespace Shop.Orders;

// An order moves from placed to shipped, and a move out of order fails with a generic exception whose
// message is the only record of which move it was.
public sealed class Lifecycle
{
    private OrderStatus status = OrderStatus.Placed;

    public void Ship()
    {
        if (status != OrderStatus.Paid)
        {
            // @sin GenericThrow
            throw new InvalidOperationException($"An order that is {status} cannot be shipped.");
        }

        status = OrderStatus.Shipped;
    }

    public void Pay()
    {
        if (status != OrderStatus.Placed)
        {
            // @righteous GenericThrow
            throw new InvalidOperationException();
        }

        status = OrderStatus.Paid;
    }
}
