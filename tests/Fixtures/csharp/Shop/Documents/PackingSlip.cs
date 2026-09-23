using Shop.Orders;

namespace Shop.Documents;

public sealed class PackingSlip(Order order)
{
    public bool IsEmpty => order.Lines.Count == 0;

    // @sin DuplicateMethod
    public IReadOnlyList<string> Rows()
    {
        var rows = new List<string>();

        foreach (var line in order.Lines)
        {
            if (line.Quantity <= 0)
            {
                continue;
            }

            rows.Add($"{line.Quantity} x {line.Sku}: {line.Subtotal.Cents / 100m:0.00}");
        }

        return rows;
    }
}
