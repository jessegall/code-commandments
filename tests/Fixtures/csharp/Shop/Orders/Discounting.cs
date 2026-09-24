namespace Shop.Orders;

using Shop.Pricing;

// Works out an order's total after its promotion.
public sealed class Discounting(int subtotal)
{
    // @sin NamespaceCycle
    public int Total(Promotion promotion)
    {
        // @sin TypeSwitch
        switch (promotion)
        {
            case PercentOff percent:
                return subtotal - subtotal * percent.Percent / 100;
            case AmountOff amount:
                return subtotal - Math.Min(amount.Amount, subtotal);
            default:
                return subtotal;
        }
    }
}
