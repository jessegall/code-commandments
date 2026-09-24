namespace Shop.Pricing;

// A promotion a basket can carry, and what it takes off the price.
public abstract class Promotion
{
    public abstract int Discount(int cents);

    // @fixed TypeSwitch
    public abstract string Label();
}

public sealed class PercentOff(int percent) : Promotion
{
    public int Percent { get; } = percent;

    public override int Discount(int cents) => cents * Percent / 100;

    // @fixed TypeSwitch
    public override string Label() => $"{Percent}% off";
}

public sealed class AmountOff(int amount) : Promotion
{
    public int Amount { get; } = amount;

    public override int Discount(int cents) => Math.Min(Amount, cents);

    // @fixed TypeSwitch
    public override string Label() => $"{Amount / 100m:C} off";
}

public static class PromotionLabels
{
    // @sin TypeSwitch
    public static string Label(Promotion promotion) => promotion switch
    {
        PercentOff p => $"{p.Percent}% off",
        AmountOff a => $"{a.Amount / 100m:C} off",
        _ => "",
    };

    public static int Pay(Promotion promotion, int cents) => cents - promotion.Discount(cents);

    // @fixed TypeSwitch
    public static string Describe(Promotion promotion) => promotion.Label();
}

// The answer a coupon check gives, its cases declared inside it.
public abstract record CouponCheck
{
    public sealed record Valid(int Cents) : CouponCheck;

    public sealed record Expired(DateOnly On) : CouponCheck;

    // @righteous TypeSwitch
    public static string Explain(CouponCheck check) => check switch
    {
        Valid v => $"saves {v.Cents}",
        Expired e => $"expired on {e.On}",
        _ => "",
    };
}
