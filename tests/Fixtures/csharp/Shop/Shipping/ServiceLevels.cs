namespace Shop.Shipping;

// How fast a parcel travels, as the carrier's numeric service code.
// @sin ConstClassEnum
public static class ServiceLevel
{
    public const int Standard = 1;
    public const int Express = 2;
    public const int Overnight = 3;
}

public static class TransitDays
{
    public static int For(int serviceLevel)
    {
        switch (serviceLevel)
        {
            case ServiceLevel.Overnight:
                return 1;
            case ServiceLevel.Express:
                return 2;
            default:
                return 5;
        }
    }
}
