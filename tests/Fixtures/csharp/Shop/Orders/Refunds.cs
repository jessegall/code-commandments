namespace Shop.Orders;

public enum RefundReason { Damaged, Late, Changed }

// A refund is worked out by switching on the reason inside the paid check, then looping the lines and
// deciding each one — the per-line rule sits four levels down.
public sealed class Refunds
{
    public long Amount(Order order, bool paid, RefundReason reason)
    {
        long cents = 0;

        if (paid)
        {
            switch (reason)
            {
                case RefundReason.Damaged:
                    foreach (var line in order.Lines)
                    {
                        // @sin DeepNesting
                        if (line.Quantity > 0)
                        {
                            cents += line.Subtotal.Cents;
                        }
                    }

                    break;
                case RefundReason.Late:
                    cents = order.Total("EUR").Cents / 10;
                    break;
                default:
                    cents = 0;
                    break;
            }
        }

        return cents;
    }
}
