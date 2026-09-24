namespace Shop.Pricing;

public sealed record CouponCode(string Code, int PercentOff);

public sealed class CouponBook
{
    private readonly Dictionary<string, CouponCode> byCode = new(StringComparer.OrdinalIgnoreCase);

    // @sin DeNulledFinder
    public CouponCode? Find(string code) => byCode.GetValueOrDefault(code);

    // @fixed DeNulledFinder
    public CouponCode Get(string code) => byCode.TryGetValue(code, out var coupon) ? coupon : throw new KeyNotFoundException(code);
}

public sealed class CouponCounter(CouponBook book)
{
    // @sin NullForgiven
    public int PercentFor(string code) => book.Find(code)!.PercentOff;

    // @sin NullForgiven
    public string Describe(string code) => $"{code}: {book.Find(code)!.PercentOff}% off";
}
