// @example NamespaceCycle good
namespace Shop.Rewards;

// Rewards issues a voucher from what it is handed — a member's id and points — and names nothing in
// Loyalty, so the one arrow left between the two runs Loyalty → Rewards.
public sealed class VoucherIssuer(VoucherCatalog catalog)
{
    // @fixed NamespaceCycle
    public string IssueTo(string memberId, int points) => $"{memberId}:{catalog.Cheapest(points).Code}";
}
