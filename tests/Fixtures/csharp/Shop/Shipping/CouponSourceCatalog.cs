using System;

namespace Shop.Shipping;

public abstract class CouponSource
{
    public abstract string Label { get; }
}

public sealed class CounterCouponSource : CouponSource
{
    public override string Label => "Counter";
}

public sealed class NewsletterCouponSource : CouponSource
{
    public override string Label => "Newsletter";
}

public sealed class AppCouponSource : CouponSource
{
    public override string Label => "App";
}

public sealed class ReferralCouponSource : CouponSource
{
    public override string Label => "Referral";
}

public sealed class CatalogueCouponSource : CouponSource
{
    public override string Label => "Catalogue";
}

public static class CouponSourceCatalog
{
    public static Type[] All()
    {
        // @sin ListMirrorsFolder
        return [typeof(CounterCouponSource), typeof(NewsletterCouponSource), typeof(AppCouponSource), typeof(ReferralCouponSource), typeof(CatalogueCouponSource)];
    }

    // @righteous ListMirrorsFolder
    public static Type[] ForKiosk()
    {
        return [typeof(CounterCouponSource), typeof(NewsletterCouponSource)];
    }
}
