# C# dependency direction — references point down the stack — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### csharp-namespace-cycle

two of the project's namespaces that each use the other — a cycle that makes them one namespace split under two names

```cs
----------[ Bad ]----------

public int Total(Promotion promotion)
{
    switch (promotion)
    {
        case PercentOff percent:
            return subtotal - subtotal * percent.Percent / 100;
        case AmountOff amount:
            return subtotal - Math.Min(amount.Amount, subtotal);
        default:
            return subtotal;
    }
}

----------[ Good ]----------

public bool Covers(Shop.Rewards.Voucher voucher) => balance >= voucher.Cost;
```

### csharp-namespace-dependency

a reference out of a declared layer into a namespace that layer did not declare it may use

```cs
----------[ Bad ]----------

public IEnumerable<string> Slugs(IEnumerable<Shop.Storefront.ProductPage> pages) => pages.Select(page => page.Slug).Distinct();

----------[ Good ]----------

public IEnumerable<Product> Matching(string term) => products.Where(product => product.Title.Contains(term, StringComparison.OrdinalIgnoreCase));
```
