namespace Shop.Storefront;

// A shelf of anything the storefront shows, the type a generic reference names.
public sealed class Shelf<T>(IReadOnlyList<T> items)
{
    public int Count => items.Count;
}
