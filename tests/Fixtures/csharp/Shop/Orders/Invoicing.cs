namespace Shop.Orders;

// Invoicing only bills lines that were actually ordered — and says so by wrapping the whole loop body
// in that condition, so the billing sits a level deeper than the loop needs.
public sealed class Invoicing
{
    // @sin FeatureEnvy
    public long Bill(Order order, List<string> journal)
    {
        long billed = 0;

        foreach (var line in order.Lines)
        {
            // @sin LoopWrappedInIf
            if (line.Quantity > 0)
            {
                billed += line.Subtotal.Cents;
                journal.Add($"{line.Sku}: {line.Subtotal.Cents}");
            }
        }

        return billed;
    }

    // @fixed LoopWrappedInIf
    // @sin FeatureEnvy
    public long BillFlat(Order order, List<string> journal)
    {
        long billed = 0;

        foreach (var line in order.Lines)
        {
            if (line.Quantity <= 0)
            {
                continue;
            }

            billed += line.Subtotal.Cents;
            journal.Add($"{line.Sku}: {line.Subtotal.Cents}");
        }

        return billed;
    }

    // @righteous LoopWrappedInIf
    // @sin FeatureEnvy
    public string? FirstBackordered(Order order)
    {
        string? found = null;

        foreach (var line in order.Lines)
        {
            if (line.Quantity > 99)
            {
                found = line.Sku;
                break;
            }
        }

        return found;
    }
}
