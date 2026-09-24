namespace Shop.Orders;

/// <summary>Splits a <see cref="ShoppingCart"/> into the parcels that need wrapping.</summary>
// @sin DanglingDocReference
public sealed class WrapStation
{
    public IReadOnlyList<string> Wrappable(IEnumerable<string> skus) => skus.Where(sku => !sku.StartsWith("DIG-")).ToList();
}

/// <summary>Splits a <see cref="Basket"/> into the parcels that need wrapping.</summary>
// @fixed DanglingDocReference
public sealed class WrapCounter
{
    public int Count(IEnumerable<string> skus) => skus.Count(sku => !sku.StartsWith("DIG-"));
}
