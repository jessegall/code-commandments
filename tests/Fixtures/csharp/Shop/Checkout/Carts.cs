namespace Shop.Checkout;

public sealed class Cart(int budgetCents)
{
    private readonly List<string> skus = [];

    public int RemainingBudget { get; private set; } = budgetCents;

    public void Add(string sku, int priceCents)
    {
        skus.Add(sku);
        RemainingBudget -= priceCents;
    }

    // @sin NamespaceDependency
    public string BackLink(Shop.Storefront.ProductPage page) => $"{page.Slug}?cart={skus.Count}";
}
