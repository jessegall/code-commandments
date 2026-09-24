namespace Shop.Pricing;

// A promotion a basket can carry, and what it takes off the price.
public abstract class Promotion
{
    // @fixed TypeSwitch
    public abstract int Discount(int cents);
}

public sealed class PercentOff(int percent) : Promotion
{
    public int Percent { get; } = percent;

    // @fixed TypeSwitch
    public override int Discount(int cents) => cents * Percent / 100;
}

public sealed class AmountOff(int amount) : Promotion
{
    public int Amount { get; } = amount;

    // @fixed TypeSwitch
    public override int Discount(int cents) => Math.Min(Amount, cents);
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

    // @fixed TypeSwitch
    public static int Pay(Promotion promotion, int cents) => cents - promotion.Discount(cents);
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
