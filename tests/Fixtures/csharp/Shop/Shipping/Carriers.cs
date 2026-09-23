using Shop.Orders;

namespace Shop.Shipping;

// Two carriers price a heavy parcel the same way — the surcharge rule was written into each carrier's
// property, so the next carrier will copy it a third time.
public sealed class PostalCarrier(int grams, bool fragile)
{
    public Money Surcharge
    {
        // @sin DuplicateMethod
        get
        {
            // @sin NestedTernary
            var cents = grams > 20_000 ? 1_500 : grams > 5_000 ? 700 : 0;

            if (fragile)
            {
                cents += cents / 2 + 250;
            }

            return new Money(cents, "EUR");
        }
    }
}

public sealed class CourierCarrier(int grams, bool fragile)
{
    public string Name => "Courier";

    public Money Surcharge
    {
        // @sin DuplicateMethod
        get
        {
            // @sin NestedTernary
            var cents = grams > 20_000 ? 1_500 : grams > 5_000 ? 700 : 0;

            if (fragile)
            {
                cents += cents / 2 + 250;
            }

            return new Money(cents, "EUR");
        }
    }
}
