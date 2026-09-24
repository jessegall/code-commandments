// @example NamespaceDependency bad
namespace Shop.Search;

using Shop.Catalog;

// Search is declared to use only the catalog; `Slugs` reaches up into the storefront above it.
public sealed class SearchIndex(IReadOnlyList<Product> products)
{
    public IEnumerable<Product> Matching(string term) => products.Where(product => product.Title.Contains(term, StringComparison.OrdinalIgnoreCase));

    // @sin NamespaceDependency
    public IEnumerable<string> Slugs(IEnumerable<Shop.Storefront.ProductPage> pages) => pages.Select(page => page.Slug).Distinct();
}
