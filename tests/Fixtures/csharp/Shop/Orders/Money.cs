namespace Shop.Orders;

/// <summary>
/// An amount in one currency's minor units — the value every price and total is written in.
/// </summary>
public sealed record Money(long Cents, string Currency)
{
    public static Money Zero(string currency) => new(0, currency);

    public Money Plus(Money other)
    {
        if (other.Currency != Currency)
        {
            throw CurrencyMismatch.For(this, other);
        }

        return this with { Cents = Cents + other.Cents };
    }
}
