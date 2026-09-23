namespace Shop.Orders;

// Checkout refuses an empty order and then carries the whole checkout inside an `else` the `throw`
// already made unnecessary.
public sealed class Checkout
{
    public Money Charge(Order order, string currency)
    {
        // @sin RedundantElse
        if (order.Lines.Count == 0)
        {
            throw EmptyOrder.For(order);
        }
        else
        {
            var total = order.Total(currency);

            return total with { Cents = total.Cents + 250 };
        }
    }

    // @fixed RedundantElse
    public Money ChargeFlat(Order order, string currency)
    {
        if (order.Lines.Count == 0)
        {
            throw EmptyOrder.For(order);
        }

        var total = order.Total(currency);

        return total with { Cents = total.Cents + 250 };
    }

    // @righteous RedundantElse
    public string Greeting(Order order, bool returning)
    {
        string text;

        if (returning)
        {
            text = $"Welcome back — order {order.Reference}";
        }
        else
        {
            text = $"Thanks for your first order, {order.Reference}";
        }

        return text;
    }
}

public sealed class EmptyOrder : InvalidOperationException
{
    private EmptyOrder(string message) : base(message) {}

    public static EmptyOrder For(Order order) => new($"Order {order.Reference} has nothing to charge.");
}
