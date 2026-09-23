namespace Shop.Pricing;

// Rounding answers a free item early — without braces — and still hangs the arithmetic off an `else`.
public static class Rounding
{
    public static long ToNickel(long cents)
    {
        // @sin RedundantElse
        if (cents <= 0)
            return 0;
        else
            return (cents + 2) / 5 * 5;
    }
}
