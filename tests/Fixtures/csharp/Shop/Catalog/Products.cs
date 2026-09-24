namespace Shop.Catalog;

public sealed record Product(string Sku, string Title, int PriceCents)
{
    public bool IsOnSale(int listCents) => PriceCents < listCents;

    // @sin NamespaceDependency
    public bool FitsIn(Shop.Checkout.Cart cart) => cart.RemainingBudget >= PriceCents;
}
