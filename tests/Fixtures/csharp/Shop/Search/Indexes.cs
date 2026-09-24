namespace Shop.Search;

using Shop.Catalog;

public sealed class SearchIndex(IReadOnlyList<Product> products)
{
    // @fixed NamespaceDependency
    public IEnumerable<Product> Matching(string term) => products.Where(product => product.Title.Contains(term, StringComparison.OrdinalIgnoreCase));

    // @sin NamespaceDependency
    public IEnumerable<string> Slugs(IEnumerable<Shop.Storefront.ProductPage> pages) => pages.Select(page => page.Slug).Distinct();
}
