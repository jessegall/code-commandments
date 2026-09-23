namespace Shop.Orders;

public sealed class Order(string reference, IReadOnlyList<OrderLine> lines)
{
    public string Reference { get; } = reference;

    public IReadOnlyList<OrderLine> Lines { get; } = lines;

    public Money Total(string currency) =>
        Lines.Aggregate(Money.Zero(currency), static (total, line) => total.Plus(line.Subtotal));
}

public sealed record OrderLine(string Sku, int Quantity, Money UnitPrice)
{
    public Money Subtotal => UnitPrice with { Cents = UnitPrice.Cents * Quantity };
}
