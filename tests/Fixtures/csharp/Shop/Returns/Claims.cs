namespace Shop.Returns;

using Shop.Warranty;

public sealed record Claim(string Serial, DateOnly Bought, string Fault);

public static class ClaimDesk
{
    public static bool IsCovered(Claim claim, WarrantyPolicy policy, DateOnly today) => policy.Covers(claim.Fault) && today <= claim.Bought.AddMonths(policy.Months);

    public static WarrantyPolicy Stricter(WarrantyPolicy first, WarrantyPolicy second) => first.Months <= second.Months ? first : second;
}
