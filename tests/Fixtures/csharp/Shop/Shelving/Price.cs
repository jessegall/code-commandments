namespace Shop.Shelving;

public sealed class Price(int cents, string currency)
{
    public int Cents { get; } = cents;

    public string Currency { get; } = currency;

    public static Price Of(int cents, string currency) => new(Math.Max(0, cents), currency.ToUpperInvariant());
}
