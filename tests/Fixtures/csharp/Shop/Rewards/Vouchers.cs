namespace Shop.Rewards;

public sealed record Voucher(string Code, int Cost);

public sealed class VoucherCatalog(IReadOnlyList<Voucher> all)
{
    public IReadOnlyList<Voucher> All => all;

    public Voucher Cheapest(int budget) => all.Where(voucher => voucher.Cost <= budget).OrderBy(voucher => voucher.Cost).First();

    // @sin NamespaceCycle
    public string IssueTo(Shop.Loyalty.Member member) => $"{member.Id}:{Cheapest(member.Points).Code}";
}
