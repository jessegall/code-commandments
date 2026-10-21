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

// An index is declared by handing the store a lambda it never runs: the store READS the lambda and writes a column
// name from it. The `!` there forgives no null, since nothing is dereferenced; it is what the compiler asks for to
// turn a nullable member into `object`, and taking it out would not compile.
public sealed class CouponIndex(string name)
{
    public string Name { get; } = name;

    public string? Season { get; set; }
}

public sealed class CouponIndexes
{
    private readonly List<string> declared = [];

    public void Unique(System.Linq.Expressions.Expression<Func<CouponIndex, object>> by) => declared.Add(by.ToString());

    // @righteous NullForgiven
    public void Declare() => Unique(index => index.Season!);

    public IReadOnlyList<string> Declared() => declared;
}
