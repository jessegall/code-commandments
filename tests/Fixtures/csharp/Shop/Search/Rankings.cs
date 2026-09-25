namespace Shop.Search;

using Shop.Catalog;

// Search may use only the catalog; a storefront shelf reached through its generic type is the storefront all the same.
public static class Rankings
{
    // @sin NamespaceDependency
    public static int Shown(Shop.Storefront.Shelf<Product> shelf) => shelf.Count;
}
