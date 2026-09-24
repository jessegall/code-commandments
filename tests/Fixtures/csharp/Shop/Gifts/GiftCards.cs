namespace Shop.Gifts;

// A gift card asks rewards what a voucher costs, one way: rewards knows nothing about gift cards.
public sealed class GiftCard(decimal balance)
{
    // @fixed NamespaceCycle
    public bool Covers(Shop.Rewards.Voucher voucher) => balance >= voucher.Cost;
}
