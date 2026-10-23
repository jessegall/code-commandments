namespace Shop.Checkout;

public sealed record CardOwner(string Name);

public interface IHasOwner
{
    CardOwner Owner { get; }
}

public sealed record OwnedCard(CardOwner Owner) : IHasOwner;

public sealed record OwnedVoucher(CardOwner Owner) : IHasOwner;

// Asking a payment source who owns it. Every arm reads the same member off the very value it matched, which is
// the question the source should answer itself.
public static class SourceOwners
{
    // @sin TypeSwitch
    public static CardOwner Of(IHasOwner source) => source switch
    {
        OwnedCard card => card.Owner,
        OwnedVoucher voucher => voucher.Owner,
        _ => throw new ArgumentOutOfRangeException(nameof(source)),
    };
}
