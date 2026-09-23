using Shop.Orders;

namespace Shop.Pricing;

// Two volume discounts walk the lines the same way and differ only in where the tiers start — two
// copies of one rule, each with its own numbers.
public static class Discounts
{
    // @sin NearDuplicateMethod
    public static long Wholesale(IReadOnlyList<OrderLine> lines)
    {
        long off = 0;

        for (var index = 0; index < lines.Count; index++)
        {
            var units = lines[index].Quantity;

            if (units >= 100)
            {
                off += lines[index].Subtotal.Cents * 15 / 100;
            }
            else if (units >= 25)
            {
                off += lines[index].Subtotal.Cents * 5 / 100;
            }
        }

        return off;
    }

    // @sin NearDuplicateMethod
    public static long Retail(IReadOnlyList<OrderLine> lines)
    {
        long saved = 0;

        for (var i = 0; i < lines.Count; i++)
        {
            var count = lines[i].Quantity;

            if (count >= 10)
            {
                saved += lines[i].Subtotal.Cents * 10 / 100;
            }
            else if (count >= 3)
            {
                saved += lines[i].Subtotal.Cents * 2 / 100;
            }
        }

        return saved;
    }
}
