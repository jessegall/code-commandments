namespace Shop.Orders;

// A payout sent to a seller, and the bank reference it went out under.
public sealed record Payout(string SellerId, int Cents, string BankReference);

// @fixed PlaceholderFilledData
public sealed record PendingPayout(string SellerId, int Cents);

public static class Payouts
{
    // @sin PlaceholderFilledData
    public static Payout Queued(string sellerId, int cents) => new Payout(sellerId, cents, "");

    // @fixed PlaceholderFilledData
    public static PendingPayout Queue(string sellerId, int cents) => new PendingPayout(sellerId, cents);
}
