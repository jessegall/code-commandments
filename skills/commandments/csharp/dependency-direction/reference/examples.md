# C# dependency direction — references point down the stack — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### csharp-namespace-cycle

two of the project's namespaces that each use the other — a cycle that makes them one namespace split under two names

```cs
----------[ Bad ]----------

// in Shop/Loyalty/Members.cs
namespace Shop.Loyalty;

using Shop.Rewards;

// A loyalty member spends points on rewards; rewards reaches back for the member below.
public sealed class Member(string id, int points)
{
    public string Id => id;

    public int Points => points;

    public Voucher Redeem(VoucherCatalog catalog) => catalog.Cheapest(points);

    public IEnumerable<Voucher> Affordable(VoucherCatalog catalog) => catalog.All.Where(voucher => voucher.Cost <= points);

    public bool CanRedeem(Voucher voucher) => voucher.Cost <= points;
}

// in Shop/Rewards/Vouchers.cs
namespace Shop.Rewards;

public sealed record Voucher(string Code, int Cost);

public sealed class VoucherCatalog(IReadOnlyList<Voucher> all)
{
    public IReadOnlyList<Voucher> All => all;

    public Voucher Cheapest(int budget) => all.Where(voucher => voucher.Cost <= budget).OrderBy(voucher => voucher.Cost).First();

    public string IssueTo(Shop.Loyalty.Member member) => $"{member.Id}:{Cheapest(member.Points).Code}";
}

----------[ Good ]----------

// in Shop/Rewards/Issuing.cs
namespace Shop.Rewards;

// Rewards issues a voucher from what it is handed — a member's id and points — and names nothing in
// Loyalty, so the one arrow left between the two runs Loyalty → Rewards.
public sealed class VoucherIssuer(VoucherCatalog catalog)
{
    public string IssueTo(string memberId, int points) => $"{memberId}:{catalog.Cheapest(points).Code}";
}
```

### csharp-namespace-dependency

a reference out of a declared layer into a namespace that layer did not declare it may use

```cs
----------[ Bad ]----------

// in Shop/Search/Indexes.cs
namespace Shop.Search;

using Shop.Catalog;

// Search is declared to use only the catalog; `Slugs` reaches up into the storefront above it.
public sealed class SearchIndex(IReadOnlyList<Product> products)
{
    public IEnumerable<Product> Matching(string term) => products.Where(product => product.Title.Contains(term, StringComparison.OrdinalIgnoreCase));

    public IEnumerable<string> Slugs(IEnumerable<Shop.Storefront.ProductPage> pages) => pages.Select(page => page.Slug).Distinct();
}

----------[ Good ]----------

// in Shop/Storefront/PageSlugs.cs
namespace Shop.Storefront;

// Listing the pages' slugs is the storefront's own work, so it lives beside the pages, and search keeps
// to the catalog it is declared to use.
public sealed class PageList(IReadOnlyList<ProductPage> pages)
{
    public ISet<string> Slugs() => pages.Select(page => page.Slug).ToHashSet();
}
```
