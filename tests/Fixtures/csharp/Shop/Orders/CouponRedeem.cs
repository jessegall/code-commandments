namespace Shop.Orders;

// Redeems a coupon code typed at the till.
public sealed class CouponRedeem
{
    private readonly Dictionary<string, int> discounts = new() { ["WELCOME"] = 10 };

    public int Percent(string? typed)
    {
        // @sin InlineThrow
        var code = (typed ?? throw new CouponMissing()).Trim().ToUpperInvariant();

        return discounts.GetValueOrDefault(code);
    }

    public string Label(string? typed)
    {
        // @righteous InlineThrow
        var code = typed ?? throw new CouponMissing();

        return $"Coupon {code}";
    }
}

public sealed class CouponMissing() : Exception("No coupon code was typed.");
