using Shop.Orders;

namespace Shop.Documents;

// The FIX for the documents' copied loop: the line layout lives in ONE method, and every document that
// lists order lines calls it instead of carrying its own copy.
public static class LineLayout
{
    // @fixed DuplicateMethod
    public static IReadOnlyList<string> Describe(IEnumerable<OrderLine> lines) =>
        lines.Where(line => line.Ordered).Select(line => line.Row()).ToList();
}

public sealed class DeliveryNote(Order order)
{
    // @fixed DuplicateMethod
    public IReadOnlyList<string> Rows() => LineLayout.Describe(order.Lines);
}

public sealed class ReturnForm(Order order)
{
    // @fixed DuplicateMethod
    public IReadOnlyList<string> Items() => LineLayout.Describe(order.Lines);
}
