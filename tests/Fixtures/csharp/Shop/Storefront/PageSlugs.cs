// @example NamespaceDependency good
namespace Shop.Storefront;

// Listing the pages' slugs is the storefront's own work, so it lives beside the pages, and search keeps
// to the catalog it is declared to use.
public sealed class PageList(IReadOnlyList<ProductPage> pages)
{
    // @fixed NamespaceDependency
    public ISet<string> Slugs() => pages.Select(page => page.Slug).ToHashSet();
}
