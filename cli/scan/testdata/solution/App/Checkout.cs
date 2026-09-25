using Core;

namespace App;

public sealed class Checkout(Prices prices)
{
    public decimal Total(string sku) => prices.Of(sku);
}
