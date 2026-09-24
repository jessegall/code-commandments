// @example NamespaceCycle bad
namespace Shop.Loyalty;

using Shop.Rewards;

// A loyalty member spends points on rewards; rewards reaches back for the member below.
public sealed class Member(string id, int points)
{
    public string Id => id;

    public int Points => points;

    // @righteous NamespaceCycle
    public Voucher Redeem(VoucherCatalog catalog) => catalog.Cheapest(points);

    public IEnumerable<Voucher> Affordable(VoucherCatalog catalog) => catalog.All.Where(voucher => voucher.Cost <= points);

    public bool CanRedeem(Voucher voucher) => voucher.Cost <= points;
}
