namespace Shop.Orders;

// What a shopper has put aside, before checkout.
public sealed record Basket(string ShopperId)
{
    // @sin MutableValueObject
    public int Items { get; set; }
}

// A basket held for a shopper: a value, so a change is a new one.
public sealed record HeldBasket(string ShopperId)
{
    // @fixed MutableValueObject
    public int Items { get; init; }

    // @fixed MutableValueObject
    public HeldBasket WithOneMore() => this with { Items = Items + 1 };
}
