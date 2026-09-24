namespace Shop.Storefront;

using Shop.Catalog;

public sealed record ProductPage(string Slug, string Heading)
{
    // @righteous NamespaceDependency
    public static ProductPage For(Product product) => new(product.Sku.ToLowerInvariant(), product.Title);
}
