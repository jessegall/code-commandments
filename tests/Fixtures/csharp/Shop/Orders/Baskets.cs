namespace Shop.Orders;

// What a shopper has put aside, before checkout.
public sealed record Basket(string ShopperId)
{
    // @sin MutableValueObject
    public int Items { get; set; }

    // @fixed MutableValueObject
    public int Count { get; init; }

    // @fixed MutableValueObject
    public Basket WithOneMore() => this with { Count = Count + 1 };
}
