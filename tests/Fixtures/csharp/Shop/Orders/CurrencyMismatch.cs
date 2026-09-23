namespace Shop.Orders;

public sealed class CurrencyMismatch : InvalidOperationException
{
    private CurrencyMismatch(string message) : base(message) {}

    public static CurrencyMismatch For(Money left, Money right) =>
        new($"Cannot add {right.Currency} to {left.Currency}.");
}
