using Shop.Orders;

namespace Shop.Documents;

// An invoice and a packing slip each list an order's lines — and each wrote its own copy of the loop,
// so a change to how a line reads has to be made twice.
public sealed class Invoice(Order order)
{
    public string Title => $"Invoice {order.Reference}";

    // @sin DuplicateMethod
    public IReadOnlyList<string> Lines()
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
